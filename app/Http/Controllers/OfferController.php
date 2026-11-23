<?php

namespace App\Http\Controllers;

use App\Http\Resources\OfferResource;
use App\Models\Conversation;
use App\Models\Offer;
use App\Models\Treatment;
use App\Models\TreatmentPackage;
use App\Models\User;
use App\Notifications\NewOfferNotification;
use App\Notifications\NotificationType;
use App\Notifications\OfferAcceptedNotification;
use App\Notifications\OfferRejectedNotification;
use App\Rules\PriceAmount;
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

        if (! $isPatient && ! Conversation::query()->accessibleToClinic($user)->whereKey($conversation->id)->exists()) {
            abort(
                403,
                'Bu görüşmedeki tekliflere erişim yetkiniz yok.'
            );
        }

        $branchIds = $isPatient ? null : $user->accessibleBranchIds($conversation->business_id);

        $offers = $conversation->offers()
            ->where('business_id', $conversation->business_id)
            ->where('patient_profile_id', $conversation->patient_profile_id)
            ->when($branchIds !== null, fn ($offers) => $offers->where(fn ($scope) => $scope
                ->whereNull('offers.branch_id')
                ->orWhereHas('branch', fn ($branches) => $branches->whereKey($branchIds)
                    ->where('business_id', $conversation->business_id)->where('status', 'active'))))
            ->with(OfferResource::DISPLAY_RELATIONS)
            ->withExists(['appointments' => fn ($query) => $query
                ->whereColumn('appointments.business_id', 'offers.business_id')
                ->whereColumn('appointments.patient_profile_id', 'offers.patient_profile_id')
                ->when($branchIds !== null, fn ($query) => $query->whereIn('appointments.branch_id', $branchIds))])
            ->latest('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => OfferResource::collection($offers),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function packageOptions(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        $this->authorizeOfferCreation($user, $conversation);

        $branchId = $conversation->branch_id;
        $branchIds = $user->accessibleBranchIds($conversation->business_id);

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
        abort_unless(
            Conversation::query()->accessibleToClinic($user)->whereKey($conversation->id)->exists(),
            403,
            'Bu görüşme için teklif oluşturma yetkiniz yok.'
        );

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
                ...PriceAmount::RULES,
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

        $offerAttributes = [
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
        ];

        $offer = DB::transaction(function () use ($conversation, $offerAttributes): Offer {
            $offer = $conversation->offers()->create($offerAttributes);
            $conversation->patientProfile?->user?->notify(
                (new NewOfferNotification($offer))->withApplicationTransaction()
            );

            return $offer;
        }, attempts: 3);

        return response()->json([
            'success' => true,
            'message' => 'Teklif başarıyla oluşturuldu.',
            'data' => new OfferResource($offer->load(OfferResource::DISPLAY_RELATIONS)),
        ], 201)->header('Cache-Control', 'private, no-store');
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

        $acceptedOffer = $this->recordPatientDecision($offer, 'accepted');

        return response()->json([
            'success' => true,
            'message' => 'Teklif kabul edildi.',
            'data' => new OfferResource($acceptedOffer->load(OfferResource::DISPLAY_RELATIONS)),
        ])->header('Cache-Control', 'private, no-store');
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

        $rejectedOffer = $this->recordPatientDecision($offer, 'rejected', $validated['rejection_reason'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Teklif reddedildi.',
            'data' => new OfferResource($rejectedOffer->load(OfferResource::DISPLAY_RELATIONS)),
        ])->header('Cache-Control', 'private, no-store');
    }

    private function recordPatientDecision(Offer $offer, string $status, ?string $rejectionReason = null): Offer
    {
        $result = DB::transaction(function () use ($offer, $status, $rejectionReason): Offer {
            $lockedOffer = Offer::query()
                ->whereKey($offer->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($lockedOffer->hasActiveClinic(), 422, 'Bu sağlık merkezi şu anda teklif işlemlerine açık değil.');

            if ($lockedOffer->status === 'expired') {
                return $lockedOffer;
            }

            if ($lockedOffer->status !== 'pending') {
                abort(
                    422,
                    $status === 'accepted' ? 'Bu teklif artık kabul edilemez.' : 'Bu teklif artık reddedilemez.'
                );
            }

            if ($lockedOffer->hasPassedDeadline()) {
                $lockedOffer->update([
                    'status' => 'expired',
                ]);

                return $lockedOffer;
            }

            $changes = [
                'status' => $status,
                'responded_at' => now(),
            ];
            if ($status === 'rejected') {
                $changes['rejection_reason'] = $rejectionReason;
            }

            $lockedOffer->update($changes);
            $this->notifyOfferCreator($lockedOffer);

            return $lockedOffer;
        }, attempts: 3);

        abort_if($result->status === 'expired', 422, 'Bu teklifin geçerlilik süresi dolmuştur.');

        return $result;
    }

    private function notifyOfferCreator(Offer $offer): void
    {
        $creator = $offer->creator;
        if (! $creator || ! $offer->conversation()->accessibleToClinic($creator)->exists()
            || ($offer->branch_id !== null && ! $creator->hasBusinessBranchAccess($offer->business_id, $offer->branch_id))) {
            return;
        }

        $accepted = $offer->status === 'accepted';
        $type = $accepted ? NotificationType::OFFER_ACCEPTED : NotificationType::OFFER_REJECTED;

        if ($this->notificationPreferenceService->isInAppEnabled($offer->business, $type)) {
            $creator->notifyNow($accepted ? new OfferAcceptedNotification($offer) : new OfferRejectedNotification($offer));
        }
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
        $treatment = Treatment::findOrFail($treatmentId);
        abort_unless($treatment->is_active, 422, 'Seçilen tedavi aktif değil.');
        abort_unless($treatment->is_offer_enabled, 422, 'Seçilen tedavi teklif oluşturmaya kapalı.');

        if ($conversation->branch_id !== null) {
            $branchTreatment = DB::table('branch_treatment')
                ->where('branch_id', $conversation->branch_id)
                ->where('treatment_id', $treatmentId)
                ->first(['is_active', 'is_offer_enabled']);

            abort_unless($branchTreatment?->is_active, 422, 'Seçilen tedavi bu şubede aktif değil.');
            abort_unless($branchTreatment->is_offer_enabled, 422, 'Seçilen tedavi bu şubede teklif oluşturmaya kapalı.');
        }
    }
}
