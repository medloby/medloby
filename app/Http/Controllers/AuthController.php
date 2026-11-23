<?php

namespace App\Http\Controllers;

use App\Models\BusinessUser;
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
        return $this->authenticate($request, false);
    }

    public function adminLogin(Request $request): JsonResponse
    {
        return $this->authenticate($request, true)->header('Cache-Control', 'private, no-store');
    }

    private function authenticate(Request $request, bool $adminOnly): JsonResponse
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

        [$user, $token] = DB::transaction(function () use ($validated, $field, $identifier, $column, $adminOnly): array {
            $user = User::query()->where($column, $identifier)->lockForUpdate()->first();

            if (! $user || $user->clinic_onboarding_pending || ! Hash::check($validated['password'], $user->password)) {
                throw ValidationException::withMessages([
                    $field => ['Giriş bilgileri hatalı.'],
                ]);
            }

            abort_if($adminOnly && ! $user->canUsePlatformSupport(), 403, 'Doğrulanmış bir platform hesabıyla giriş yapın.');

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
                $membership = $user->businessMemberships()->where('business_user.business_id', $user->staff_business_id)
                    ->withStaffCrmAccess()
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
                'user' => $adminOnly ? $user->only(['id', 'name']) : $user,
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
        $ownedClinics = $user->hasVerifiedEmail()
            ? $user->businessMemberships->filter(fn (BusinessUser $member) => $member->role === 'business_owner'
                && $member->is_active && $member->business?->allowsCrmAccess())->sortBy('business_id')
            : collect();
        $primaryClinic = $ownedClinics->first(fn (BusinessUser $member) => $user->login_code === 'klinik-'.$member->business_id);
        $ownerCrmBusinessId = ($primaryClinic ?? $ownedClinics->first())?->business_id;
        $defaultCrmBusinessId = $user->staff_business_id === null ? $ownerCrmBusinessId
            : $user->businessMemberships()->where('business_user.business_id', $user->staff_business_id)
                ->withStaffCrmAccess()->value('business_user.business_id');

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user->toArray() + [
                    'owner_crm_business_id' => $ownerCrmBusinessId,
                    'default_crm_business_id' => $defaultCrmBusinessId,
                ],
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
