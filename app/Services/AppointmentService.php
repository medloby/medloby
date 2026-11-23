<?php

namespace App\Services;

use App\Exceptions\AppointmentRuleViolation;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchAppointmentSetting;
use App\Models\Doctor;
use App\Models\Treatment;
use Carbon\Carbon;

class AppointmentService
{
    public function __construct(
        protected AppointmentAvailabilityService $availabilityService,
        private AppointmentWaitingList $waitingList,
    ) {}

    /**
     * Randevu oluşturur.
     *
     * Müsaitlik kontrolünün tamamı
     * AppointmentAvailabilityService tarafından yapılır.
     */
    public function createAppointment(array $data): Appointment
    {
        return $this->availabilityService->runBookingTransaction(function () use ($data) {
            $startsAt = Appointment::calendarDateTime(Carbon::parse($data['starts_at']));
            $source = $data['source'] ?? 'medloby';
            $requireOnlineBookable = $data['require_online_bookable'] ?? true;

            $doctor = Doctor::query()
                ->lockForUpdate()->find($data['doctor_id'] ?? null);
            $patientProfileId = isset($data['patient_profile_id']) ? (int) $data['patient_profile_id'] : null;
            $this->availabilityService->lockPatient($patientProfileId);

            $branch = Branch::query()->where('business_id', $data['business_id'])->find($data['branch_id']);
            if (! $branch || $branch->status !== 'active' || ! $branch->business?->allowsCrmAccess()) {
                throw new AppointmentRuleViolation('Bu şube şu anda randevuya açık değil.');
            }

            if (! $doctor || ! $doctor->person()->where('business_id', $branch->business_id)->exists()) {
                throw new AppointmentRuleViolation(
                    'Geçerli bir doktor seçilmelidir.'
                );
            }
            if ($source === 'medloby' && ! $doctor->is_public) {
                throw new AppointmentRuleViolation('Seçilen doktor çevrimiçi randevuya açık değil.');
            }

            $treatment = Treatment::query()
                ->find($data['treatment_id'] ?? null);

            if (! $treatment || ! $treatment->is_active) {
                throw new AppointmentRuleViolation(
                    'Geçerli bir tedavi seçilmelidir.'
                );
            }

            $durationMinutes = $this->availabilityService->getDurationMinutes(
                $doctor,
                (int) $data['branch_id'],
                $treatment
            );

            if ($durationMinutes === null || $durationMinutes <= 0) {
                throw new AppointmentRuleViolation(
                    'Randevu süresi belirlenemedi.'
                );
            }

            $endsAt = $startsAt->copy()->addMinutes($durationMinutes);

            $available = $this->availabilityService->isAvailable(
                doctor: $doctor,
                branchId: (int) $data['branch_id'],
                treatment: $treatment,
                startsAt: $startsAt,
                requireOnlineBookable: $requireOnlineBookable
            );

            if (! $available) {
                throw new AppointmentRuleViolation(
                    'Seçilen tarih ve saat için randevu uygun değil.'
                );
            }
            $this->availabilityService->ensurePatientAvailable(
                $patientProfileId, $startsAt, $endsAt, 'Seçilen tarih ve saat için randevu uygun değil.',
            );

            $status = $data['status'] ?? ($source === 'medloby' && $requireOnlineBookable
                ? BranchAppointmentSetting::initialStatusForOnlineBooking($branch->id)
                : 'pending');

            $appointmentData = [
                'business_id' => $data['business_id'],
                'branch_id' => $data['branch_id'],
                'patient_profile_id' => $patientProfileId,
                'doctor_id' => $data['doctor_id'],
                'treatment_id' => $data['treatment_id'],
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => $status,
                'source' => $source,
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

            $this->waitingList->completeForAppointment($appointment);

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
            throw new AppointmentRuleViolation(
                'Randevu süresi belirlenemedi.'
            );
        }

        return $startsAt->copy()->addMinutes($durationMinutes);
    }
}
