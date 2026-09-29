<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\Doctor;
use App\Models\PatientProfile;
use App\Models\Treatment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AppointmentBookingService
{
    public function __construct(
        protected AppointmentAvailabilityService $availabilityService
    ) {
    }

    /**
     * Yeni randevu oluşturur.
     */
    public function book(
        int $businessId,
        int $branchId,
        PatientProfile $patient,
        Doctor $doctor,
        Treatment $treatment,
        Carbon $startsAt,
        string $source = 'medloby',
        bool $requireOnlineBookable = true,
        ?string $notes = null
    ): Appointment {
        return DB::transaction(function () use (
            $businessId,
            $branchId,
            $patient,
            $doctor,
            $treatment,
            $startsAt,
            $source,
            $requireOnlineBookable,
            $notes
        ) {
            $startsAt = $startsAt->copy();

            $durationMinutes = $this->availabilityService
                ->getDurationMinutes(
                    $doctor,
                    $branchId,
                    $treatment
                );

            if ($durationMinutes === null || $durationMinutes <= 0) {
                throw new RuntimeException(
                    'Bu tedavi için geçerli bir süre tanımlanmamış.'
                );
            }

            $endsAt = $startsAt->copy()->addMinutes($durationMinutes);

            $isAvailable = $this->availabilityService->isAvailable(
                $doctor,
                $branchId,
                $treatment,
                $startsAt,
                null,
                $requireOnlineBookable
            );

            if (! $isAvailable) {
                throw new RuntimeException(
                    'Seçilen tarih ve saat için randevu müsait değil.'
                );
            }

            $appointment = Appointment::create([
                'business_id' => $businessId,
                'branch_id' => $branchId,
                'patient_profile_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'treatment_id' => $treatment->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => 'pending',
                'source' => $source,
                'patient_name' => trim(
    $patient->first_name . ' ' . $patient->last_name
),
'patient_phone' => $patient->phone,
'patient_email' => $patient->user?->email,
'notes' => $notes,
            ]);

            AppointmentStatusHistory::create([
                'appointment_id' => $appointment->id,
                'changed_by_user_id' => null,
                'old_status' => null,
                'new_status' => 'pending',
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
}