<?php

namespace App\Services;

use App\Exceptions\AppointmentRuleViolation;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Offer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AppointmentLifecycleService
{
    public function __construct(
        protected AppointmentAvailabilityService $availabilityService,
        protected AppointmentNotificationService $notificationService,
        protected AppointmentSlotAvailabilityService $slotAvailabilityService,
    ) {}

    private function ensurePermission(
        Appointment $appointment,
        ?User $changedBy,
        string $permissionName,
        ?Branch $calendarBranch
    ): void {
        if (! $changedBy) {
            throw new AppointmentRuleViolation(
                'Bu randevu işlemi için yetkili kullanıcı gereklidir.'
            );
        }

        $allowed = $calendarBranch
            ? $calendarBranch->id === $appointment->branch_id
                && $calendarBranch->business_id === $appointment->business_id
                && $changedBy->canManageClinicAppointments($calendarBranch, $permissionName)
            : $changedBy->hasBusinessPermission($appointment->business_id, $permissionName);
        if (! $allowed) {
            throw new AppointmentRuleViolation(
                'Bu randevu işlemi için yetkiniz bulunmuyor.'
            );
        }
    }

    private function ensurePatientAction(Appointment $appointment, ?User $patient, string $action): void
    {
        abort_unless($patient && $appointment->belongsToPatient($patient), 404);

        if (! in_array($action, $appointment->patientActions(Appointment::clinicLocalNow()), true)) {
            throw new AppointmentRuleViolation('Bu işlem için şubenizle iletişime geçin. Çevrimiçi işlem kapalı veya süresi dolmuş olabilir.');
        }
    }

    /** @return list<string> */
    public function patientRescheduleSlots(Appointment $appointment, User $patient, Carbon $date): array
    {
        $this->ensurePatientAction($appointment, $patient, 'reschedule');

        $doctor = $appointment->doctor;
        $treatment = $appointment->treatment;
        $slots = $this->slotAvailabilityService->getAvailableSlots($doctor, $appointment->branch_id, $treatment, $date,
            ignoreAppointmentId: $appointment->id, patientProfileId: $appointment->patient_profile_id);

        return collect($slots)
            ->reject(fn (Carbon $start): bool => $start->equalTo($appointment->starts_at))
            ->map(fn (Carbon $start): string => $start->toDateTimeString())->values()->all();
    }

    public function confirm(
        Appointment $appointment,
        ?User $changedBy = null,
        ?string $reason = null,
        ?string $notes = null,
        ?Branch $calendarBranch = null
    ): Appointment {
        return DB::transaction(function () use (
            $appointment,
            $changedBy,
            $reason,
            $notes,
            $calendarBranch
        ) {
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);

            $this->ensurePermission(
                $appointment,
                $changedBy,
                'appointments.confirm',
                $calendarBranch
            );

            if ($appointment->status !== 'pending') {
                throw new AppointmentRuleViolation(
                    'Yalnızca bekleyen randevular onaylanabilir.'
                );
            }

            $oldStatus = $appointment->status;

            $appointment->update([
                'status' => 'confirmed',
            ]);

            AppointmentStatusHistory::create([
                'appointment_id' => $appointment->id,
                'changed_by_user_id' => $changedBy?->id,
                'old_status' => $oldStatus,
                'new_status' => 'confirmed',
                'reason' => $reason ?? 'Randevu onaylandı.',
                'notes' => $notes,
                'changed_at' => now(),
            ]);

            $this->notificationService->notifyAppointmentConfirmed($appointment);

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

    public function complete(
        Appointment $appointment,
        ?User $changedBy = null,
        ?string $reason = null,
        ?string $notes = null,
        ?Branch $calendarBranch = null
    ): Appointment {
        return DB::transaction(function () use (
            $appointment,
            $changedBy,
            $reason,
            $notes,
            $calendarBranch
        ) {
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);

            $this->ensurePermission(
                $appointment,
                $changedBy,
                'appointments.complete',
                $calendarBranch
            );

            if ($appointment->status !== 'confirmed') {
                throw new AppointmentRuleViolation(
                    'Yalnızca onaylanmış randevular tamamlanabilir.'
                );
            }

            $oldStatus = $appointment->status;

            $appointment->update([
                'status' => 'completed',
            ]);

            AppointmentStatusHistory::create([
                'appointment_id' => $appointment->id,
                'changed_by_user_id' => $changedBy?->id,
                'old_status' => $oldStatus,
                'new_status' => 'completed',
                'reason' => $reason ?? 'Randevu tamamlandı.',
                'notes' => $notes,
                'changed_at' => now(),
            ]);

            $this->notificationService->notifyAppointmentCompleted($appointment);

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

    public function cancel(
        Appointment $appointment,
        string $cancellationReason,
        ?User $changedBy = null,
        ?string $notes = null,
        ?Branch $calendarBranch = null,
        bool $byPatient = false,
    ): Appointment {
        return DB::transaction(function () use (
            $appointment,
            $cancellationReason,
            $changedBy,
            $notes,
            $calendarBranch,
            $byPatient,
        ) {
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);

            if ($byPatient) {
                $this->ensurePatientAction($appointment, $changedBy, 'cancel');
            } else {
                $this->ensurePermission($appointment, $changedBy, 'appointments.cancel', $calendarBranch);
            }

            if (! in_array(
                $appointment->status,
                ['pending', 'confirmed'],
                true
            )) {
                throw new AppointmentRuleViolation(
                    'Yalnızca bekleyen veya onaylanmış randevular iptal edilebilir.'
                );
            }

            $cancellationReason = trim($cancellationReason);

            if ($cancellationReason === '') {
                throw new AppointmentRuleViolation(
                    'Randevu iptal nedeni boş bırakılamaz.'
                );
            }

            $oldStatus = $appointment->status;

            $appointment->update([
                'status' => 'cancelled',
                'cancellation_reason' => $cancellationReason,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $changedBy?->id,
            ]);

            AppointmentStatusHistory::create([
                'appointment_id' => $appointment->id,
                'changed_by_user_id' => $changedBy?->id,
                'old_status' => $oldStatus,
                'new_status' => 'cancelled',
                'reason' => $cancellationReason,
                'notes' => $notes,
                'changed_at' => now(),
            ]);

            $this->notificationService->notifyAppointmentCancelled($appointment, $byPatient);

            return $appointment->fresh([
                'business',
                'branch',
                'patientProfile',
                'doctor',
                'treatment',
                'cancelledBy',
                'statusHistories',
            ]);
        });
    }

    public function noShow(
        Appointment $appointment,
        ?User $changedBy = null,
        ?string $reason = null,
        ?string $notes = null,
        ?Branch $calendarBranch = null
    ): Appointment {
        return DB::transaction(function () use (
            $appointment,
            $changedBy,
            $reason,
            $notes,
            $calendarBranch
        ) {
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);

            $this->ensurePermission(
                $appointment,
                $changedBy,
                'appointments.mark_no_show',
                $calendarBranch
            );

            if ($appointment->status !== 'confirmed') {
                throw new AppointmentRuleViolation(
                    'Yalnızca onaylanmış randevular gelmedi olarak işaretlenebilir.'
                );
            }

            $oldStatus = $appointment->status;

            $appointment->update([
                'status' => 'no_show',
            ]);

            AppointmentStatusHistory::create([
                'appointment_id' => $appointment->id,
                'changed_by_user_id' => $changedBy?->id,
                'old_status' => $oldStatus,
                'new_status' => 'no_show',
                'reason' => $reason ?? 'Hasta randevuya gelmedi.',
                'notes' => $notes,
                'changed_at' => now(),
            ]);

            $this->notificationService->notifyAppointmentNoShow($appointment);

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

    public function reschedule(
        Appointment $appointment,
        Carbon $newStartsAt,
        ?User $changedBy = null,
        ?string $reason = null,
        ?string $notes = null,
        bool $requireOnlineBookable = true,
        ?Branch $calendarBranch = null,
        bool $byPatient = false,
    ): Appointment {
        return $this->availabilityService->runBookingTransaction(function () use (
            $appointment,
            $newStartsAt,
            $changedBy,
            $reason,
            $notes,
            $requireOnlineBookable,
            $calendarBranch,
            $byPatient,
        ) {
            $offer = $appointment->offer_id ? Offer::query()->lockForUpdate()->find($appointment->offer_id) : null;
            $doctor = Doctor::query()->lockForUpdate()->find($appointment->doctor_id);
            $this->availabilityService->lockPatient($appointment->patient_profile_id);
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);

            if ($byPatient) {
                $this->ensurePatientAction($appointment, $changedBy, 'reschedule');
            } else {
                $this->ensurePermission($appointment, $changedBy, 'appointments.reschedule', $calendarBranch);
            }

            if (! in_array(
                $appointment->status,
                ['pending', 'confirmed'],
                true
            )) {
                throw new AppointmentRuleViolation(
                    'Yalnızca bekleyen veya onaylanmış randevuların tarihi değiştirilebilir.'
                );
            }

            $treatment = $appointment->treatment;

            if (! $doctor || ! $treatment) {
                throw new AppointmentRuleViolation(
                    'Randevu tarihini değiştirmek için doktor ve tedavi bilgisi gereklidir.'
                );
            }
            $newStartsAt = Appointment::calendarDateTime($newStartsAt);
            if ($newStartsAt->equalTo($appointment->starts_at)) {
                throw new AppointmentRuleViolation('Randevu için farklı bir tarih veya saat seçin.');
            }

            $durationMinutes = $this->availabilityService
                ->getDurationMinutes(
                    $doctor,
                    $appointment->branch_id,
                    $treatment
                );

            if ($durationMinutes === null || $durationMinutes <= 0) {
                throw new AppointmentRuleViolation(
                    'Randevu süresi belirlenemedi.'
                );
            }

            if (! $this->availabilityService->isAvailable(
                $doctor,
                $appointment->branch_id,
                $treatment,
                $newStartsAt,
                $appointment->id,
                $byPatient || $requireOnlineBookable
            )) {
                throw new AppointmentRuleViolation(
                    'Seçilen yeni tarih ve saat için randevu müsait değil.'
                );
            }

            $newEndsAt = $newStartsAt
                ->copy()
                ->addMinutes($durationMinutes);

            $this->availabilityService->ensurePatientAvailable(
                $appointment->patient_profile_id, $newStartsAt, $newEndsAt, 'Seçilen yeni tarih ve saat için randevu müsait değil.',
                ignoreAppointmentId: $appointment->id, packageOfferId: $offer?->package_snapshot !== null ? $offer->id : null,
            );

            $oldStatus = $appointment->status;

            $newAppointment = Appointment::create([
                'business_id' => $appointment->business_id,
                'branch_id' => $appointment->branch_id,
                'patient_profile_id' => $appointment->patient_profile_id,
                'doctor_id' => $appointment->doctor_id,
                'treatment_id' => $appointment->treatment_id,
                'offer_id' => $offer?->id,
                'starts_at' => $newStartsAt,
                'ends_at' => $newEndsAt,
                'status' => $oldStatus,
                'source' => $appointment->source,
                'patient_name' => $appointment->patient_name,
                'patient_phone' => $appointment->patient_phone,
                'patient_email' => $appointment->patient_email,
                'notes' => $appointment->notes,
                'rescheduled_from_appointment_id' => $appointment->id,
                'rescheduled_at' => now(),
            ]);

            AppointmentStatusHistory::create([
                'appointment_id' => $newAppointment->id,
                'changed_by_user_id' => $changedBy?->id,
                'old_status' => null,
                'new_status' => $oldStatus,
                'reason' => $reason
                    ?? 'Randevu yeni tarih ve saate taşındı.',
                'notes' => $notes,
                'changed_at' => now(),
            ]);

            $appointment->update([
                'status' => 'rescheduled',
                'rescheduled_at' => now(),
            ]);

            AppointmentStatusHistory::create([
                'appointment_id' => $appointment->id,
                'changed_by_user_id' => $changedBy?->id,
                'old_status' => $oldStatus,
                'new_status' => 'rescheduled',
                'reason' => $reason
                    ?? 'Randevu tarihi değiştirildi.',
                'notes' => $notes,
                'changed_at' => now(),
            ]);

            $this->notificationService->notifyAppointmentRescheduled($newAppointment, $byPatient);

            return $newAppointment->fresh([
                'business',
                'branch',
                'patientProfile',
                'doctor',
                'treatment',
                'rescheduledFrom',
                'statusHistories',
            ]);
        });
    }
}
