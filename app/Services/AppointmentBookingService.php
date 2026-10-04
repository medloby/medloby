<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\Doctor;
use App\Models\Offer;
use App\Models\PatientProfile;
use App\Models\Treatment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use App\Services\AppointmentNotificationService;

class AppointmentBookingService
{
    public function __construct(
    protected AppointmentAvailabilityService $availabilityService,
    protected AppointmentNotificationService $notificationService
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
        ?string $notes = null,
        ?Offer $offer = null
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
            $notes,
            $offer
        ) {
            if ($offer) {
                $this->validateOfferContext(
                    $offer,
                    $businessId,
                    $branchId,
                    $patient,
                    $treatment
                );
            }

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
                'offer_id' => $offer?->id,
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

            DB::afterCommit(function () use ($appointment) {
    $this->notificationService->notifyNewAppointment(
        $appointment->fresh([
            'business',
            'branch',
            'patientProfile',
            'doctor.person',
            'treatment',
            'offer',
        ])
    );
});
            
return $appointment->fresh([
                'business',
                'branch',
                'patientProfile',
                'doctor',
                'treatment',
                'offer',
                'statusHistories',
            ]);
        });
    }

    /**
     * Teklifin randevu oluşturma bağlamıyla uyumlu olduğunu doğrular.
     */
    protected function validateOfferContext(
        Offer $offer,
        int $businessId,
        int $branchId,
        PatientProfile $patient,
        Treatment $treatment
    ): void {
        if ($offer->status !== 'accepted') {
            throw new RuntimeException(
                'Randevu oluşturmak için teklifin kabul edilmiş olması gerekir.'
            );
        }

        if (
            $offer->valid_until &&
            $offer->valid_until->isPast()
        ) {
            throw new RuntimeException(
                'Bu teklifin geçerlilik süresi dolmuştur.'
            );
        }

        if ($offer->business_id !== $businessId) {
            throw new RuntimeException(
                'Teklif farklı bir işletmeye aittir.'
            );
        }

        if (
            $offer->branch_id !== null &&
            $offer->branch_id !== $branchId
        ) {
            throw new RuntimeException(
                'Teklif farklı bir şubeye aittir.'
            );
        }

        if ($offer->patient_profile_id !== $patient->id) {
            throw new RuntimeException(
                'Teklif farklı bir hastaya aittir.'
            );
        }

        if (
            $offer->treatment_id !== null &&
            $offer->treatment_id !== $treatment->id
        ) {
            throw new RuntimeException(
                'Teklifteki tedavi ile seçilen tedavi uyuşmuyor.'
            );
        }

        $existingAppointment = Appointment::query()
            ->where('offer_id', $offer->id)
            ->exists();

        if ($existingAppointment) {
            throw new RuntimeException(
                'Bu teklif daha önce randevuya dönüştürülmüş.'
            );
        }
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
        $branchBelongsToBusiness = DB::table('branches')
            ->where('id', $branchId)
            ->where('business_id', $businessId)
            ->exists();

        if (! $branchBelongsToBusiness) {
            throw new RuntimeException(
                'Seçilen şube bu işletmeye ait değil.'
            );
        }

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

        if (! $treatment->is_active) {
            throw new RuntimeException(
                'Seçilen tedavi aktif değil.'
            );
        }

        $branchTreatment = DB::table('branch_treatment')
            ->where('branch_id', $branchId)
            ->where('treatment_id', $treatment->id)
            ->first();

        if (! $branchTreatment) {
            throw new RuntimeException(
                'Seçilen tedavi bu şubede bulunmuyor.'
            );
        }

        if (! (bool) $branchTreatment->is_active) {
            throw new RuntimeException(
                'Seçilen tedavi bu şubede aktif değil.'
            );
        }

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