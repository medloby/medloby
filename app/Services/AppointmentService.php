<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Treatment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AppointmentService
{
    public function __construct(
        protected AppointmentAvailabilityService $availabilityService
    ) {
    }

    /**
     * Randevu oluşturur.
     *
     * Müsaitlik kontrolünün tamamı
     * AppointmentAvailabilityService tarafından yapılır.
     */
    public function createAppointment(array $data): Appointment
    {
        return DB::transaction(function () use ($data) {
            $startsAt = Carbon::parse($data['starts_at']);

            $doctor = Doctor::query()
                ->find($data['doctor_id'] ?? null);

            if (! $doctor) {
                throw new RuntimeException(
                    'Geçerli bir doktor seçilmelidir.'
                );
            }

            $treatment = Treatment::query()
                ->find($data['treatment_id'] ?? null);

            if (! $treatment) {
                throw new RuntimeException(
                    'Geçerli bir tedavi seçilmelidir.'
                );
            }

            $durationMinutes = $this->availabilityService->getDurationMinutes(
                $doctor,
                (int) $data['branch_id'],
                $treatment
            );

            if ($durationMinutes === null || $durationMinutes <= 0) {
                throw new RuntimeException(
                    'Randevu süresi belirlenemedi.'
                );
            }

            $endsAt = $startsAt->copy()->addMinutes($durationMinutes);

            $available = $this->availabilityService->isAvailable(
                doctor: $doctor,
                branchId: (int) $data['branch_id'],
                treatment: $treatment,
                startsAt: $startsAt,
                requireOnlineBookable: $data['require_online_bookable'] ?? true
            );

            if (! $available) {
                throw new RuntimeException(
                    'Seçilen tarih ve saat için randevu uygun değil.'
                );
            }

            $appointmentData = [
                'business_id' => $data['business_id'],
                'branch_id' => $data['branch_id'],
                'patient_profile_id' => $data['patient_profile_id'] ?? null,
                'doctor_id' => $data['doctor_id'],
                'treatment_id' => $data['treatment_id'],
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => $data['status'] ?? 'pending',
                'source' => $data['source'] ?? 'medloby',
                'patient_name' => $data['patient_name'] ?? null,
                'patient_phone' => $data['patient_phone'] ?? null,
                'patient_email' => $data['patient_email'] ?? null,
                'notes' => $data['notes'] ?? null,
            ];

            $appointment = Appointment::create($appointmentData);

            $appointment->statusHistories()->create([
                'changed_by_user_id' => $data['created_by_user_id'] ?? null,
                'old_status' => null,
                'new_status' => $appointment->status,
                'reason' => 'Randevu oluşturuldu.',
                'changed_at' => now(),
            ]);

            return $appointment->fresh([
                'business',
                'branch',
                'patientProfile',
                'doctor',
                'treatment',
                'statusHistories',
            ]);
        });
    }

    /**
     * Belirli bir doktor ve tedavi için
     * başlangıç saatinden randevu bitişini hesaplar.
     */
    public function calculateEndTime(
        Doctor $doctor,
        int $branchId,
        Treatment $treatment,
        Carbon $startsAt
    ): Carbon {
        $durationMinutes = $this->availabilityService->getDurationMinutes(
            $doctor,
            $branchId,
            $treatment
        );

        if ($durationMinutes === null || $durationMinutes <= 0) {
            throw new RuntimeException(
                'Randevu süresi belirlenemedi.'
            );
        }

        return $startsAt->copy()->addMinutes($durationMinutes);
    }
}