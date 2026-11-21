<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Offer;
use App\Models\Treatment;
use App\Models\TreatmentPackage;
use App\Models\User;
use App\Notifications\NewOfferNotification;
use App\Notifications\NotificationType;
use App\Notifications\OfferAcceptedNotification;
use App\Notifications\OfferRejectedNotification;
use App\Services\BusinessNotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OfferController extends Controller
{
    public function __construct(
        protected BusinessNotificationPreferenceService $notificationPreferenceService
    ) {}

    public function index(
        Request $request,
        Conversation $conversation
    ): JsonResponse {
        $user = $request->user();

        $isPatient = $conversation->patientProfile()
            ->where('user_id', $user->id)
            ->exists();

        $isBusinessUser = $user->businessMemberships()
            ->where('business_id', $conversation->business_id)
            ->where('is_active', true)
            ->exists();

        if (! $isPatient && ! $isBusinessUser) {
            abort(
                403,
                'Bu görüşmedeki tekliflere erişim yetkiniz yok.'
            );
        }

        $branchIds = $isPatient ? null : $user->accessibleBranchIds($conversation->business_id);

        $offers = $conversation->offers()
            ->with([
                'business',
                'branch',
                'patientProfile',
                'treatment',
                'creator',
            ])
            ->withExists(['appointments' => fn ($query) => $query
                ->whereColumn('appointments.business_id', 'offers.business_id')
                ->whereColumn('appointments.patient_profile_id', 'offers.patient_profile_id')
                ->when($branchIds !== null, fn ($query) => $query->whereIn('appointments.branch_id', $branchIds))])
            ->latest('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $offers,
        ]);
    }

    public function packageOptions(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        $this->authorizeOfferCreation($user, $conversation);

        $branchId = $conversation->branch_id;
        $branchIds = $user->accessibleBranchIds($conversation->business_id);
        abort_if($branchId !== null && ! in_array($branchId, $branchIds, true), 403);

        $packages = TreatmentPackage::query()
            ->availableForEnquiries()
            ->where('business_id', $conversation->business_id)
            ->where(fn ($query) => $query->whereNull('branch_id')
                ->orWhereIn('branch_id', $branchId === null ? $branchIds : [$branchId]))
            ->when($branchId !== null, fn ($query) => $query
                ->whereDoesntHave('treatments', fn ($treatments) => $treatments
                    ->whereDoesntHave('branches', fn ($branches) => $branches->whereKey($branchId)
                        ->where('branch_treatment.is_active', true))))
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'price', 'currency']);

        return response()->json([
            'success' => true,
            'data' => ['packages' => $packages],
        ])->header('Cache-Control', 'private, no-store');
    }

    private function authorizeOfferCreation(User $user, Conversation $conversation): void
    {
        abort_unless($user->businessMemberships()
            ->where('business_id', $conversation->business_id)
            ->where('is_active', true)
            ->exists(), 403, 'Bu görüşme için teklif oluşturma yetkiniz yok.');

        abort_unless($conversation->status === 'open', 422, 'Kapalı bir görüşmeye teklif oluşturulamaz.');
    }

    public function store(
        Request $request,
        Conversation $conversation
    ): JsonResponse {
        $user = $request->user();
        $this->authorizeOfferCreation($user, $conversation);

        $validated = $request->validate([
            'treatment_id' => [
                'required_without:treatment_package_id',
                'prohibits:treatment_package_id',
                'integer',
                'exists:treatments,id',
            ],
            'treatment_package_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:treatment_packages,id',
            ],
            'title' => [
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
                'max:10000',
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0',
            ],
            'currency' => [
                'required',
                'string',
                'size:3',
            ],
            'valid_until' => [
                'nullable',
                'date',
                'after:now',
            ],
        ]);

        $branchId = $conversation->branch_id;
        $package = null;
        $snapshot = null;

        if (isset($validated['treatment_package_id'])) {
            $package = TreatmentPackage::findOrFail($validated['treatment_package_id']);
            $branchId ??= $package->branch_id;
            abort_if($branchId !== null && ! in_array($branchId, $user->accessibleBranchIds($conversation->business_id), true), 403);
            $snapshot = $this->packageSnapshot($package, $conversation, $branchId);
        } else {
            $this->validateTreatmentForConversation($conversation, (int) $validated['treatment_id']);
        }

        $offer = Offer::create([
            'conversation_id' => $conversation->id,
            'business_id' => $conversation->business_id,
            'branch_id' => $branchId,
            'patient_profile_id' => $conversation->patient_profile_id,
            'treatment_id' => $validated['treatment_id'] ?? null,
            'treatment_package_id' => $package?->id,
            'package_snapshot' => $snapshot,
            'created_by' => $user->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'amount' => $validated['amount'],
            'currency' => strtoupper($validated['currency']),
            'valid_until' => $validated['valid_until'] ?? null,
            'status' => 'pending',
        ]);

        /*
         * Teklif oluşturulduktan sonra bağlı hastaya
         * veritabanı bildirimi gönderilir.
         */
        $patientUser = $conversation->patientProfile()
            ->with('user')
            ->first()
            ?->user;

        if ($patientUser) {
            $patientUser->notify(
                new NewOfferNotification($offer)
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Teklif başarıyla oluşturuldu.',
            'data' => $offer->load([
                'business',
                'branch',
                'patientProfile',
                'treatment',
                'creator',
            ]),
        ], 201);
    }

    public function accept(
        Request $request,
        Offer $offer
    ): JsonResponse {
        $user = $request->user();

        $isPatient = $offer->patientProfile()
            ->where('user_id', $user->id)
            ->exists();

        if (! $isPatient) {
            abort(
                403,
                'Bu teklifi kabul etme yetkiniz yok.'
            );
        }

        DB::transaction(function () use ($offer) {
            $lockedOffer = Offer::query()
                ->whereKey($offer->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOffer->status !== 'pending') {
                abort(
                    422,
                    'Bu teklif artık kabul edilemez.'
                );
            }

            if (
                $lockedOffer->valid_until &&
                $lockedOffer->valid_until->isPast()
            ) {
                $lockedOffer->update([
                    'status' => 'expired',
                ]);

                return;
            }

            $lockedOffer->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);
        });

        $freshOffer = $offer->fresh();

        if ($freshOffer->status === 'expired') {
            abort(
                422,
                'Bu teklifin geçerlilik süresi dolmuştur.'
            );
        }

        /*
         * Teklif kabul edildiğinde bildirimi,
         * teklifi oluşturan kullanıcıya göndeririz.
         *
         * Bildirim tercihi kapalıysa hiçbir bildirim
         * oluşturulmaz.
         */
        if (
            $this->notificationPreferenceService->isInAppEnabled(
                $freshOffer->business,
                NotificationType::OFFER_ACCEPTED
            )
        ) {
            $creator = $freshOffer->creator;

            if ($creator) {
                $creator->notify(
                    new OfferAcceptedNotification($freshOffer)
                );
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Teklif kabul edildi.',
            'data' => $freshOffer->load([
                'business',
                'branch',
                'patientProfile',
                'treatment',
                'creator',
            ]),
        ]);
    }

    public function reject(
        Request $request,
        Offer $offer
    ): JsonResponse {
        $user = $request->user();

        $isPatient = $offer->patientProfile()
            ->where('user_id', $user->id)
            ->exists();

        if (! $isPatient) {
            abort(
                403,
                'Bu teklifi reddetme yetkiniz yok.'
            );
        }

        $validated = $request->validate([
            'rejection_reason' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        DB::transaction(function () use (
            $offer,
            $validated
        ) {
            $lockedOffer = Offer::query()
                ->whereKey($offer->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOffer->status !== 'pending') {
                abort(
                    422,
                    'Bu teklif artık reddedilemez.'
                );
            }

            if (
                $lockedOffer->valid_until &&
                $lockedOffer->valid_until->isPast()
            ) {
                $lockedOffer->update([
                    'status' => 'expired',
                ]);

                return;
            }

            $lockedOffer->update([
                'status' => 'rejected',
                'responded_at' => now(),
                'rejection_reason' => $validated['rejection_reason'] ?? null,
            ]);
        });

        $freshOffer = $offer->fresh();

        if ($freshOffer->status === 'expired') {
            abort(
                422,
                'Bu teklifin geçerlilik süresi dolmuştur.'
            );
        }

        /*
         * Teklif reddedildiğinde bildirimi,
         * teklifi oluşturan kullanıcıya göndeririz.
         *
         * Bildirim tercihi kapalıysa hiçbir bildirim
         * oluşturulmaz.
         */
        if (
            $this->notificationPreferenceService->isInAppEnabled(
                $freshOffer->business,
                NotificationType::OFFER_REJECTED
            )
        ) {
            $creator = $freshOffer->creator;

            if ($creator) {
                $creator->notify(
                    new OfferRejectedNotification($freshOffer)
                );
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Teklif reddedildi.',
            'data' => $freshOffer->load([
                'business',
                'branch',
                'patientProfile',
                'treatment',
                'creator',
            ]),
        ]);
    }

    private function packageSnapshot(TreatmentPackage $package, Conversation $conversation, ?int $branchId): array
    {
        if ($package->business_id !== $conversation->business_id
            || ($package->branch_id !== null && $package->branch_id !== $branchId)
            || ! $package->is_offer_enabled
            || ! $package->isPublishedInCatalog()) {
            throw ValidationException::withMessages([
                'treatment_package_id' => 'Bu paket görüşmede teklif olarak kullanılamaz.',
            ]);
        }

        $treatments = $package->treatments()->orderByPivot('sort_order')->orderBy('treatments.id')->get();
        $eligibleCount = Treatment::query()->eligibleForPackage($branchId)->whereKey($treatments->modelKeys())->count();

        if ($treatments->isEmpty() || $eligibleCount !== $treatments->count()) {
            throw ValidationException::withMessages([
                'treatment_package_id' => 'Paketin en az bir tedavisi olmalı ve tüm tedavileri seçilen kapsamda aktif olmalıdır.',
            ]);
        }

        $services = $package->services()->orderByPivot('sort_order')->orderBy('package_services.id')->get();

        if ($services->contains(fn ($service) => ! $service->is_active
            || ($service->business_id !== null && $service->business_id !== $package->business_id))) {
            throw ValidationException::withMessages([
                'treatment_package_id' => 'Paket geçersiz veya başka işletmeye ait bir hizmet içeriyor.',
            ]);
        }

        return [
            'treatment_package_id' => $package->id,
            'branch_id' => $branchId,
            'name' => $package->name,
            'description' => $package->description,
            'included_services' => $package->included_services,
            'excluded_services' => $package->excluded_services,
            'duration_days' => $package->duration_days,
            'includes_hotel' => $package->includes_hotel,
            'includes_transfer' => $package->includes_transfer,
            'treatments' => $treatments->map(fn ($treatment) => [
                'treatment_id' => $treatment->id,
                'name' => $treatment->name,
                'quantity' => (int) $treatment->pivot->quantity,
                'notes' => $treatment->pivot->notes,
            ])->all(),
            'services' => $services->map(fn ($service) => [
                'package_service_id' => $service->id,
                'name' => $service->name,
                'unit' => $service->unit,
                'quantity' => (int) $service->pivot->quantity,
                'notes' => $service->pivot->notes,
            ])->all(),
        ];
    }

    /**
     * Teklifte seçilen tedavinin görüşmenin şubesi için
     * gerçekten aktif ve kullanılabilir olduğunu doğrular.
     */
    protected function validateTreatmentForConversation(
        Conversation $conversation,
        int $treatmentId
    ): void {
        $treatmentIsActive = DB::table('treatments')
            ->where('id', $treatmentId)
            ->where('is_active', true)
            ->exists();

        if (! $treatmentIsActive) {
            abort(
                422,
                'Seçilen tedavi aktif değil.'
            );
        }

        if ($conversation->branch_id !== null) {
            $branchTreatmentIsActive = DB::table('branch_treatment')
                ->where('branch_id', $conversation->branch_id)
                ->where('treatment_id', $treatmentId)
                ->where('is_active', true)
                ->exists();

            if (! $branchTreatmentIsActive) {
                abort(
                    422,
                    'Seçilen tedavi bu şubede aktif değil.'
                );
            }
        }
    }
}
