<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\ClinicContractAcceptance;
use App\Models\PlatformContract;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class BusinessRegistrationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8),
            ],

            'business_name' => ['required', 'string', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'business_email' => ['nullable', 'email', 'max:255'],
            'business_phone' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'url', 'max:255'],

            'country_code' => ['nullable', 'string', 'size:2'],
            'city' => ['required', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'address' => ['required', 'string'],
            'postal_code' => ['nullable', 'string', 'max:20'],

            'branch_name' => ['nullable', 'string', 'max:255'],

            'contract_accepted' => ['accepted'],
        ]);

        $contract = PlatformContract::query()
            ->where('contract_type', 'clinic_membership')
            ->where('status', 'published')
            ->where('is_required', true)
            ->where(function ($query) {
                $query
                    ->whereNull('effective_at')
                    ->orWhere('effective_at', '<=', now());
            })
            ->where(function ($query) {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->latest('published_at')
            ->first();

        if (! $contract) {
            return response()->json([
                'success' => false,
                'message' => 'Şu anda kabul edilebilir aktif klinik üyelik sözleşmesi bulunmuyor.',
            ], 422);
        }

        $result = DB::transaction(function () use (
            $validated,
            $request,
            $contract
        ) {
            $user = User::create([
                'name' => $validated['owner_name'],
                'email' => strtolower($validated['owner_email']),
                'password' => Hash::make($validated['password']),
            ]);

            $businessSlug = $this->uniqueBusinessSlug(
                $validated['business_name']
            );

            $business = Business::create([
                'name' => $validated['business_name'],
                'slug' => $businessSlug,
                'type' => $validated['business_type'] ?? 'clinic',
                'description' => $validated['description'] ?? null,
                'email' => isset($validated['business_email'])
                    ? strtolower($validated['business_email'])
                    : null,
                'phone' => $validated['business_phone'] ?? null,
                'website' => $validated['website'] ?? null,
                'country_code' => strtoupper(
                    $validated['country_code'] ?? 'TR'
                ),
                'city' => $validated['city'],
                'district' => $validated['district'] ?? null,
                'address' => $validated['address'],
                'postal_code' => $validated['postal_code'] ?? null,

                // Klinik admin onayı bekliyor.
                'status' => 'pending',
                'is_verified' => false,
                'verified_at' => null,
            ]);

            $branchName = $validated['branch_name']
                ?? $validated['business_name'];

            $branch = Branch::create([
                'business_id' => $business->id,
                'name' => $branchName,
                'slug' => $this->uniqueBranchSlug(
                    $branchName,
                    $business->id
                ),
                'phone' => $validated['business_phone'] ?? null,
                'email' => isset($validated['business_email'])
                    ? strtolower($validated['business_email'])
                    : null,
                'country_code' => strtoupper(
                    $validated['country_code'] ?? 'TR'
                ),
                'city' => $validated['city'],
                'district' => $validated['district'] ?? null,
                'address' => $validated['address'],
                'postal_code' => $validated['postal_code'] ?? null,
                'status' => 'pending',
            ]);

            $membership = BusinessUser::create([
                'business_id' => $business->id,
                'user_id' => $user->id,
                'role' => 'business_owner',
                'is_active' => true,
            ]);

            ClinicContractAcceptance::create([
                'business_id' => $business->id,
                'user_id' => $user->id,
                'platform_contract_id' => $contract->id,
                'contract_version' => $contract->version,
                'accepted_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'acceptance_method' => 'checkbox',
                'is_accepted' => true,
            ]);

            $token = $user
                ->createToken('medloby-auth')
                ->plainTextToken;

            return [
                'user' => $user,
                'business' => $business,
                'branch' => $branch,
                'membership' => $membership,
                'contract' => $contract,
                'token' => $token,
            ];
        });

        event(new Registered($result['user']));

        return response()->json([
            'success' => true,
            'message' => 'Klinik başvurusu ve sözleşme kabulü başarıyla kaydedildi. Başvurunuz admin onayı bekliyor.',
            'data' => [
                'token' => $result['token'],
                'token_type' => 'Bearer',
                'user' => $result['user'],
                'business' => $result['business'],
                'branch' => $result['branch'],
                'membership' => $result['membership'],
                'contract' => [
                    'id' => $result['contract']->id,
                    'title' => $result['contract']->title,
                    'version' => $result['contract']->version,
                    'accepted' => true,
                ],
                'approval_status' => 'pending',
            ],
        ], 201);
    }

    private function uniqueBusinessSlug(string $name): string
    {
        $baseSlug = Str::slug($name) ?: 'saglik-merkezi';
        $slug = $baseSlug;
        $counter = 2;

        while (Business::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function uniqueBranchSlug(
        string $name,
        int $businessId
    ): string {
        $baseSlug = Str::slug($name) ?: 'merkez';
        $slug = $baseSlug;
        $counter = 2;

        while (
            Branch::where('business_id', $businessId)
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}