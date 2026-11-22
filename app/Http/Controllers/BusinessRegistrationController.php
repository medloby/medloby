<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\ClinicContractAcceptance;
use App\Models\PlatformContract;
use App\Models\User;
use App\Notifications\ClinicApplicationNotification;
use App\Notifications\NotificationType;
use App\Rules\AccountPassword;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BusinessRegistrationController extends Controller
{
    public function requestApproval(Request $request): JsonResponse
    {
        if (is_string($request->input('owner_email'))) {
            $request->merge(['owner_email' => Str::lower($request->input('owner_email'))]);
        }

        $validated = $request->validate([
            'owner_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'business_name' => ['required', 'string', 'max:255'],
            'membership_plan' => ['nullable', 'string', Rule::in(array_keys(Business::MEMBERSHIP_PLANS))],
            'business_phone' => ['required', 'string', 'max:30', 'regex:/\A(?=(?:\D*\d){7,})[+0-9() .\-]{7,30}\z/'],
            'contract_accepted' => ['accepted'],
            'platform_contract_id' => ['required', 'integer'],
        ]);

        return $this->register($request, $validated, pendingCredentials: true);
    }

    public function contract(): JsonResponse
    {
        $contract = $this->currentClinicContract();
        abort_unless($contract, 404, 'Yayımlanmış bir klinik üyelik sözleşmesi bulunamadı.');

        return response()->json([
            'success' => true,
            'data' => $contract->only(['id', 'title', 'version', 'content']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (is_string($request->input('owner_email'))) {
            $request->merge(['owner_email' => strtolower($request->input('owner_email'))]);
        }

        $validated = $request->validate([
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['bail', 'required', 'string', 'confirmed', new AccountPassword],

            'business_name' => ['required', 'string', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:50'],
            'membership_plan' => ['nullable', 'string', Rule::in(array_keys(Business::MEMBERSHIP_PLANS))],
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
            'platform_contract_id' => ['sometimes', 'required', 'integer'],
        ]);

        return $this->register($request, $validated, pendingCredentials: false);
    }

    private function register(Request $request, array $validated, bool $pendingCredentials): JsonResponse
    {
        $contract = $this->currentClinicContract();

        if (! $contract) {
            return response()->json([
                'success' => false,
                'message' => 'Şu anda kabul edilebilir aktif klinik üyelik sözleşmesi bulunmuyor.',
            ], 422);
        }

        if (isset($validated['platform_contract_id']) && (int) $validated['platform_contract_id'] !== $contract->id) {
            return response()->json([
                'success' => false,
                'message' => 'Üyelik sözleşmesi güncellendi. Güncel metni inceleyip tekrar onaylayın.',
            ], 409);
        }

        $result = DB::transaction(function () use (
            $validated,
            $request,
            $contract,
            $pendingCredentials,
        ) {
            $user = new User([
                'name' => $validated['owner_name'] ?? $validated['business_name'],
                'email' => strtolower($validated['owner_email']),
                'password' => Hash::make($pendingCredentials ? Str::random(48) : $validated['password']),
            ]);
            $user->clinic_onboarding_pending = $pendingCredentials;
            $user->save();

            $businessSlug = $this->uniqueBusinessSlug(
                $validated['business_name']
            );

            $business = Business::create([
                'name' => $validated['business_name'],
                'slug' => $businessSlug,
                'type' => $validated['business_type'] ?? 'clinic',
                'membership_plan' => $validated['membership_plan'] ?? null,
                'description' => $validated['description'] ?? null,
                'email' => isset($validated['business_email'])
                    ? strtolower($validated['business_email'])
                    : ($pendingCredentials ? $user->email : null),
                'phone' => $validated['business_phone'] ?? null,
                'website' => $validated['website'] ?? null,
                'country_code' => strtoupper(
                    $validated['country_code'] ?? 'TR'
                ),
                'city' => $validated['city'] ?? null,
                'district' => $validated['district'] ?? null,
                'address' => $validated['address'] ?? null,
                'postal_code' => $validated['postal_code'] ?? null,
                'status' => 'pending',
                'is_verified' => false,
                'verified_at' => null,
            ]);

            $branchName = $validated['branch_name']
                ?? $validated['business_name'];

            $branch = Branch::create([
                'business_id' => $business->id,
                'name' => $branchName,
                'slug' => $business->uniqueBranchSlug($branchName),
                'phone' => $validated['business_phone'] ?? null,
                'email' => isset($validated['business_email'])
                    ? strtolower($validated['business_email'])
                    : ($pendingCredentials ? $user->email : null),
                'country_code' => strtoupper(
                    $validated['country_code'] ?? 'TR'
                ),
                'city' => $validated['city'] ?? null,
                'district' => $validated['district'] ?? null,
                'address' => $validated['address'] ?? null,
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

            $token = $pendingCredentials ? null : $user->createToken('medloby-auth')->plainTextToken;

            $administrators = User::query()->where('is_admin', true)->whereNotNull('email_verified_at')->get();
            Notification::send($administrators, new ClinicApplicationNotification($business, NotificationType::CLINIC_APPLICATION_SUBMITTED));

            return [
                'user' => $user,
                'business' => $business,
                'branch' => $branch,
                'membership' => $membership,
                'contract' => $contract,
                'token' => $token,
            ];
        });

        if ($pendingCredentials) {
            return response()->json([
                'success' => true,
                'message' => 'Klinik başvurunuz alındı. Onaydan sonra giriş bilgileri e-posta adresinize gönderilecek.',
                'data' => ['approval_status' => 'pending'],
            ], 201);
        }

        rescue(fn () => event(new Registered($result['user'])));

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

    private function currentClinicContract(): ?PlatformContract
    {
        return PlatformContract::query()
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
            ->orderByDesc('id')
            ->first();
    }

    private function uniqueBusinessSlug(string $name): string
    {
        $baseSlug = Str::slug($name) ?: 'saglik-merkezi';
        $slug = $baseSlug;
        $counter = 2;

        while (Business::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
