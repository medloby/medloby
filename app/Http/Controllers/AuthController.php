<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\AccountPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $field = $request->exists('identifier') ? 'identifier' : 'email';
        $validated = $request->validate([
            $field => $field === 'email' ? ['required', 'email', 'max:255'] : ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'new_password' => ['bail', 'sometimes', 'required', 'string', 'confirmed', 'different:password', new AccountPassword],
        ]);
        $identifier = $validated[$field];
        $column = $field === 'email' || str_contains($identifier, '@') ? 'email' : 'login_code';
        if ($column === 'login_code') {
            $identifier = Str::lower($identifier);
        }

        [$user, $token] = DB::transaction(function () use ($validated, $field, $identifier, $column): array {
            $user = User::query()->where($column, $identifier)->lockForUpdate()->first();

            if (! $user || $user->clinic_onboarding_pending || ! Hash::check($validated['password'], $user->password)) {
                throw ValidationException::withMessages([
                    $field => ['Giriş bilgileri hatalı.'],
                ]);
            }

            if ($user->temporary_password_expires_at !== null) {
                if ($user->temporary_password_expires_at->isPast()) {
                    throw ValidationException::withMessages([$field => ['Geçici şifrenizin süresi doldu. Şifremi unuttum bağlantısını kullanın.']]);
                }
                if (! isset($validated['new_password'])) {
                    return [$user, null];
                }

                $user->forceFill([
                    'password' => Hash::make($validated['new_password']),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                    'temporary_password_expires_at' => null,
                ])->save();
            }

            if ($user->staff_business_id !== null) {
                $membership = $user->businessMemberships()->where('business_id', $user->staff_business_id)
                    ->whereIn('role', ['staff', 'manager'])->where('is_active', true)
                    ->whereHas('business', fn ($query) => $query->where('status', 'active')->where('is_verified', true))
                    ->whereHas('branches', fn ($query) => $query->where('branches.status', 'active')
                        ->where('branches.business_id', $user->staff_business_id)->where('business_user_branch.is_active', true))
                    ->exists();
                abort_unless($membership, 403, 'Personel hesabınızın aktif şube erişimi yok.');
            }

            $user->tokens()->delete();

            return [$user, $user->createToken('medloby-auth')->plainTextToken];
        }, attempts: 3);

        if ($token === null) {
            return response()->json(['success' => true, 'data' => ['password_change_required' => true]]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Giriş başarılı.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => $user,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->load([
            'businessMemberships.business',
        ]);
        $user->loadExists('patientProfile');
        $ownerCrmBusinessId = $user->hasVerifiedEmail()
            ? $user->businessMemberships->sortBy('business_id')->first(fn ($member) => $member->role === 'business_owner'
                && $member->is_active && $member->business?->allowsCrmAccess())?->business_id
            : null;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user->toArray() + ['owner_crm_business_id' => $ownerCrmBusinessId],
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()
            ->currentAccessToken()
            ?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Çıkış başarılı.',
        ]);
    }
}
