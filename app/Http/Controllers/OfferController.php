<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Offer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfferController extends Controller
{
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

        if ($offer->status !== 'pending') {
            abort(
                422,
                'Bu teklif artık kabul edilemez.'
            );
        }

        if (
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

        $offer->update([
            'status' => 'accepted',
            'responded_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Teklif kabul edildi.',
            'data' => $offer->fresh()->load([
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

        if ($offer->status !== 'pending') {
            abort(
                422,
                'Bu teklif artık reddedilemez.'
            );
        }

        if (
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

        $validated = $request->validate([
            'rejection_reason' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $offer->status = 'rejected';
        $offer->responded_at = now();
        $offer->rejection_reason = $validated['rejection_reason'] ?? null;
        $offer->save();

        return response()->json([
            'success' => true,
            'message' => 'Teklif reddedildi.',
            'data' => $offer->fresh()->load([
                'business',
                'branch',
                'patientProfile',
                'treatment',
                'creator',
            ]),
        ]);
    }
}