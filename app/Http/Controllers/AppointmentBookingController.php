<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\Offer;
use App\Services\AppointmentBookingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AppointmentBookingController extends Controller
{
    public function __construct(
        protected AppointmentBookingService $bookingService
    ) {
    }

    public function store(
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
                'Bu teklif üzerinden randevu oluşturma yetkiniz yok.'
            );
        }

        if ($offer->status !== 'accepted') {
            abort(
                422,
                'Randevu oluşturmak için teklifin kabul edilmiş olması gerekir.'
            );
        }

        if (
            $offer->valid_until &&
            $offer->valid_until->isPast()
        ) {
            abort(
                422,
                'Bu teklifin geçerlilik süresi dolmuştur.'
            );
        }

        if (! $offer->treatment_id) {
            abort(
                422,
                'Bu teklife bağlı bir tedavi bulunmuyor.'
            );
        }

        if ($offer->appointment()->exists()) {
            abort(
                422,
                'Bu teklif daha önce randevuya dönüştürülmüş.'
            );
        }

        $validated = $request->validate([
            'branch_id' => [
                'required',
                'integer',
                'exists:branches,id',
            ],
            'doctor_id' => [
                'required',
                'integer',
                'exists:doctors,id',
            ],
            'starts_at' => [
                'required',
                'date',
                'after:now',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        if (
            $offer->branch_id !== null &&
            (int) $validated['branch_id'] !== (int) $offer->branch_id
        ) {
            abort(
                422,
                'Bu teklif farklı bir şube için oluşturulmuştur.'
            );
        }

        $patient = $offer->patientProfile;

        if (! $patient) {
            abort(
                422,
                'Teklife bağlı hasta profili bulunamadı.'
            );
        }

        $doctor = Doctor::findOrFail(
            $validated['doctor_id']
        );

        try {
            $appointment = $this->bookingService->book(
                $offer->business_id,
                (int) $validated['branch_id'],
                $patient,
                $doctor,
                $offer->treatment,
                Carbon::parse($validated['starts_at']),
                'medloby',
                true,
                $validated['notes'] ?? null,
                $offer
            );

            return response()->json([
                'success' => true,
                'message' => 'Randevu başarıyla oluşturuldu.',
                'data' => [
                    'appointment' => $appointment,
                    'offer' => $offer->fresh()->load([
                        'business',
                        'branch',
                        'patientProfile',
                        'treatment',
                        'creator',
                        'appointment',
                    ]),
                ],
            ], 201);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}