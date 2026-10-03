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
            $this->validateBookingContext(
                $businessId,
                $branchId,
                $doctor,
                $treatment,
                $requireOnlineBookable
            );

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

    /**
     * Randevunun işletme, şube, doktor ve tedavi
     * bağlamının birbiriyle uyumlu olduğunu doğrular.
     */
    protected function validateBookingContext(
        int $businessId,
        int $branchId,
        Doctor $doctor,
        Treatment $treatment,
        bool $requireOnlineBookable
    ): void {
        // Şube gerçekten bu işletmeye mi ait?
        $branchBelongsToBusiness = DB::table('branches')
            ->where('id', $branchId)
            ->where('business_id', $businessId)
            ->exists();

        if (! $branchBelongsToBusiness) {
            throw new RuntimeException(
                'Seçilen şube bu işletmeye ait değil.'
            );
        }

        // Doktorun bağlı olduğu kişi kaydı bu işletmeye mi ait?
        $doctorBelongsToBusiness = DB::table('doctors')
            ->join(
                'people',
                'people.id',
                '=',
                'doctors.person_id'
            )
            ->where('doctors.id', $doctor->id)
            ->where('people.business_id', $businessId)
            ->exists();

        if (! $doctorBelongsToBusiness) {
            throw new RuntimeException(
                'Seçilen doktor bu işletmeye ait değil.'
            );
        }

        // Doktor bu şubede aktif olarak görev yapıyor mu?
        $doctorBelongsToBranch = DB::table('doctor_branch')
            ->where('doctor_id', $doctor->id)
            ->where('branch_id', $branchId)
            ->where('status', 'active')
            ->exists();

        if (! $doctorBelongsToBranch) {
            throw new RuntimeException(
                'Seçilen doktor bu şubede aktif olarak çalışmıyor.'
            );
        }

        // Tedavi gerçekten mevcut ve aktif mi?
        if (! $treatment->is_active) {
            throw new RuntimeException(
                'Seçilen tedavi aktif değil.'
            );
        }

        // Tedavi seçilen şubeye bağlı mı?
        $branchTreatment = DB::table('branch_treatment')
            ->where('branch_id', $branchId)
            ->where('treatment_id', $treatment->id)
            ->first();

        if (! $branchTreatment) {
            throw new RuntimeException(
                'Seçilen tedavi bu şubede bulunmuyor.'
            );
        }

        // Tedavi bu şubede aktif mi?
        if (! (bool) $branchTreatment->is_active) {
            throw new RuntimeException(
                'Seçilen tedavi bu şubede aktif değil.'
            );
        }

        // Online randevu isteniyorsa tedavi online randevuya açık mı?
        if (
            $requireOnlineBookable &&
            ! (bool) $branchTreatment->is_online_bookable
        ) {
            throw new RuntimeException(
                'Seçilen tedavi bu şubede online randevuya açık değil.'
            );
        }
    }
}