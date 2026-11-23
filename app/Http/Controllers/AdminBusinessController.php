<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\BusinessReview;
use App\Models\User;
use App\Notifications\ClinicApplicationNotification;
use App\Notifications\ClinicApplicationRejectedNotification;
use App\Notifications\ClinicOwnerCredentialsNotification;
use App\Notifications\NotificationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminBusinessController extends Controller
{
    /**
     * Admin için bekleyen klinik başvurularını listeler.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(
            max((int) $request->input('per_page', 20), 1),
            100
        );

        $businesses = Business::query()
            ->where('status', 'pending')
            ->with([
                'branches',
                'memberships.user',
                'contractAcceptances.platformContract',
            ])
            ->latest()
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through($this->clinicWithPlan(...));

        return response()->json([
            'success' => true,
            'data' => $businesses,
        ]);
    }

    public function directory(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'required', 'in:pending,active,suspended,rejected'],
            'search' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'plan' => ['sometimes', 'required', Rule::in([...array_keys(Business::MEMBERSHIP_PLANS), 'unselected'])],
        ]);
        $businesses = Business::query()
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['search']), fn ($query) => $query->whereLike('name', '%'.$filters['search'].'%'))
            ->when(isset($filters['city']), fn ($query) => $query->whereLike('city', '%'.$filters['city'].'%'))
            ->when(isset($filters['plan']), fn ($query) => $query->where('membership_plan', $filters['plan'] === 'unselected' ? null : $filters['plan']))
            ->select(['id', 'name', 'status', 'city', 'district', 'membership_plan'])
            ->withCount(['branches', 'branches as active_branches_count' => fn ($query) => $query->where('status', 'active')])
            ->orderBy('name')->orderBy('id')
            ->paginate(20)
            ->through($this->clinicWithPlan(...));

        return response()->json(['success' => true, 'data' => $businesses])->header('Cache-Control', 'private, no-store');
    }

    /**
     * Admin için tek bir klinik başvurusunun detaylarını gösterir.
     */
    public function show(Business $business): JsonResponse
    {
        $business->load([
            'branches',
            'memberships.user',
            'contractAcceptances.platformContract',
            'reviews' => fn ($reviews) => $reviews->orderByRaw('CASE WHEN reviewed_at IS NULL THEN 1 ELSE 0 END')
                ->latest('reviewed_at')->orderByDesc('id'),
            'reviews.reviewedBy',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->clinicWithPlan($business),
        ])->header('Cache-Control', 'private, no-store');
    }

    private function clinicWithPlan(Business $business): array
    {
        return $business->toArray() + ['membership_plan' => $business->membershipPlanLabel()];
    }

    /**
     * Bekleyen klinik başvurusunu onaylar.
     */
    public function approve(
        Request $request,
        Business $business
    ): JsonResponse {
        $admin = $request->user();

        $result = DB::transaction(function () use ($business, $admin) {
            $business = Business::query()->lockForUpdate()->findOrFail($business->getKey());

            if ($business->status !== 'pending') {
                throw ValidationException::withMessages([
                    'business' => [
                        'Sadece bekleyen klinik başvuruları onaylanabilir.',
                    ],
                ]);
            }

            $hasAcceptedContract = $business
                ->contractAcceptances()
                ->where('is_accepted', true)
                ->exists();

            if (! $hasAcceptedContract) {
                throw ValidationException::withMessages([
                    'contract' => [
                        'Klinik başvurusu onaylanmadan önce geçerli bir sözleşme kabulü bulunmalıdır.',
                    ],
                ]);
            }

            $previousStatus = $business->status;

            $business->update([
                'status' => 'active',
                'is_verified' => true,
                'verified_at' => now(),
            ]);

            $business->branches()->update([
                'status' => 'active',
            ]);

            $review = BusinessReview::create([
                'business_id' => $business->id,
                'reviewed_by_user_id' => $admin->id,
                'decision' => 'approved',
                'reviewed_at' => now(),
                'notes' => 'Klinik başvurusu admin tarafından onaylandı.',
                'rejection_reason' => null,
                'previous_status' => $previousStatus,
                'new_status' => 'active',
            ]);

            $this->notifyOwners($business, NotificationType::CLINIC_APPLICATION_APPROVED);
            $this->provisionPendingOwner($business);

            return [
                'business' => $business->fresh([
                    'branches',
                    'contractAcceptances.platformContract',
                ]),
                'review' => $review,
            ];
        }, attempts: 3);

        return response()->json([
            'success' => true,
            'message' => 'Klinik başvurusu başarıyla onaylandı.',
            'data' => $result,
        ]);
    }

    /**
     * Bekleyen klinik başvurusunu reddeder.
     */
    public function reject(
        Request $request,
        Business $business
    ): JsonResponse {
        $validated = $request->validate([
            'rejection_reason' => [
                'required',
                'string',
                'min:10',
                'max:5000',
            ],
            'applicant_rejection_reason' => ['nullable', 'string', 'max:5000'],
            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ]);

        $admin = $request->user();

        $result = DB::transaction(function () use (
            $business,
            $admin,
            $validated
        ) {
            $business = Business::query()->lockForUpdate()->findOrFail($business->getKey());

            if ($business->status !== 'pending') {
                throw ValidationException::withMessages([
                    'business' => [
                        'Sadece bekleyen klinik başvuruları reddedilebilir.',
                    ],
                ]);
            }

            $previousStatus = $business->status;

            $business->update([
                'status' => 'rejected',
                'is_verified' => false,
                'verified_at' => null,
            ]);

            $business->branches()->update([
                'status' => 'rejected',
            ]);

            $review = BusinessReview::create([
                'business_id' => $business->id,
                'reviewed_by_user_id' => $admin->id,
                'decision' => 'rejected',
                'reviewed_at' => now(),
                'notes' => $validated['notes'] ?? null,
                'rejection_reason' => $validated['rejection_reason'],
                'applicant_rejection_reason' => $validated['applicant_rejection_reason'] ?? null,
                'previous_status' => $previousStatus,
                'new_status' => 'rejected',
            ]);

            $this->notifyOwners($business, NotificationType::CLINIC_APPLICATION_REJECTED, $review);

            return [
                'business' => $business->fresh([
                    'branches',
                    'contractAcceptances.platformContract',
                ]),
                'review' => $review,
            ];
        }, attempts: 3);

        return response()->json([
            'success' => true,
            'message' => 'Klinik başvurusu reddedildi.',
            'data' => $result,
        ]);
    }

    private function notifyOwners(Business $application, string $type, ?BusinessReview $rejection = null): void
    {
        $owners = User::query()->whereHas('businessMemberships', fn ($query) => $query
            ->where('business_id', $application->id)
            ->where('role', 'business_owner')
            ->where('is_active', true))->get();

        Notification::send($owners, new ClinicApplicationNotification($application, $type));

        if ($rejection === null) {
            return;
        }

        $applicants = $owners->filter(fn (User $owner): bool => $owner->clinic_onboarding_pending
            && filled($owner->email) && $owner->email === $application->email);

        if ($applicants->isNotEmpty()) {
            Notification::send($applicants, (new ClinicApplicationRejectedNotification(
                $application->id,
                $rejection->id,
                $rejection->applicant_rejection_reason,
            ))->withApplicationTransaction());
        }
    }

    private function provisionPendingOwner(Business $business): void
    {
        $owner = User::query()->where('clinic_onboarding_pending', true)
            ->whereHas('businessMemberships', fn ($query) => $query->where('business_id', $business->id)
                ->where('role', 'business_owner')->where('is_active', true))
            ->lockForUpdate()->first();

        if ($owner === null) {
            return;
        }

        $temporaryPassword = Str::random(24);
        $owner->forceFill([
            'login_code' => 'klinik-'.$business->id,
            'password' => $temporaryPassword,
            'clinic_onboarding_pending' => false,
            'temporary_password_expires_at' => now()->addHours(48),
        ])->save();
        $owner->notify((new ClinicOwnerCredentialsNotification($temporaryPassword))->withApplicationTransaction());
    }
}
