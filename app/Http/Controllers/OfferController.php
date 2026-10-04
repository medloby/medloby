<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Offer;
use App\Notifications\NewOfferNotification;
use App\Notifications\NotificationType;
use App\Notifications\OfferAcceptedNotification;
use App\Services\BusinessNotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OfferController extends Controller
{
    public function __construct(
        protected BusinessNotificationPreferenceService $notificationPreferenceService
    ) {
    }

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

        $offers = $conversation->offers()
            ->with([
                'business',
                'branch',
                'patientProfile',
                'treatment',
                'creator',
            ])
            ->latest('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $offers,
        ]);
    }

    public function store(
        Request $request,
        Conversation $conversation
    ): JsonResponse {
        $user = $request->user();

        $isBusinessUser = $user->businessMemberships()
            ->where('business_id', $conversation->business_id)
            ->where('is_active', true)
            ->exists();

        if (! $isBusinessUser) {
            abort(
                403,
                'Bu görüşme için teklif oluşturma yetkiniz yok.'
            );
        }

        if ($conversation->status !== 'open') {
            abort(
                422,
                'Kapalı bir görüşmeye teklif oluşturulamaz.'
            );
        }

        $validated = $request->validate([
            'treatment_id' => [
                'required',
                'integer',
                'exists:treatments,id',
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

        $this->validateTreatmentForConversation(
            $conversation,
            (int) $validated['treatment_id']
        );

        $offer = Offer::create([
            'conversation_id' => $conversation->id,
            'business_id' => $conversation->business_id,
            'branch_id' => $conversation->branch_id,
            'patient_profile_id' => $conversation->patient_profile_id,
            'treatment_id' => $validated['treatment_id'],
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

        /*
         * Süresi dolmuş teklifin expired olarak kalıcı şekilde
         * işaretlenmesi transaction dışında yapılır.
         */
        if (
            $offer->status === 'pending' &&
            $offer->valid_until &&
            $offer->valid_until->isPast()
        ) {
            $offer->update([
                'status' => 'expired',
            ]);

            abort(
                422,
                'Bu teklifin geçerlilik süresi dolmuştur.'
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

        /*
         * Süresi dolmuş teklif için kalıcı expired durumu
         * transaction dışında yazılır.
         */
        if (
            $offer->status === 'pending' &&
            $offer->valid_until &&
            $offer->valid_until->isPast()
        ) {
            $offer->update([
                'status' => 'expired',
            ]);

            abort(
                422,
                'Bu teklifin geçerlilik süresi dolmuştur.'
            );
        }

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
                'rejection_reason' =>
                    $validated['rejection_reason'] ?? null,
            ]);
        });

        $freshOffer = $offer->fresh();

        if ($freshOffer->status === 'expired') {
            abort(
                422,
                'Bu teklifin geçerlilik süresi dolmuştur.'
            );
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