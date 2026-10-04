<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AppointmentLifecycleService
{
    public function __construct(
        protected AppointmentAvailabilityService $availabilityService,
        protected AppointmentNotificationService $notificationService
    ) {
    }

    private function ensurePermission(
        Appointment $appointment,
        ?User $changedBy,
        string $permissionName
    ): void {
        if (! $changedBy) {
            throw new RuntimeException(
                'Bu randevu işlemi için yetkili kullanıcı gereklidir.'
            );
        }

        if (! $changedBy->hasBusinessPermission(
            $appointment->business_id,
            $permissionName
        )) {
            throw new RuntimeException(
                'Bu randevu işlemi için yetkiniz bulunmuyor.'
            );
        }
    }

    public function confirm(
        Appointment $appointment,
        ?User $changedBy = null,
        ?string $reason = null,
        ?string $notes = null
    ): Appointment {
        $result = DB::transaction(function () use (
            $appointment,
            $changedBy,
            $reason,
            $notes
        ) {
            $appointment->refresh();

            $this->ensurePermission(
                $appointment,
                $changedBy,
                'appointments.confirm'
            );

            if ($appointment->status !== 'pending') {
                throw new RuntimeException(
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

            return $appointment->fresh([
                'business',
                'branch',
                'patientProfile',
                'doctor',
                'treatment',
                'statusHistories',
            ]);
        });

        $this->notificationService
            ->notifyAppointmentConfirmed($result);

        return $result;
    }

    public function complete(
        Appointment $appointment,
        ?User $changedBy = null,
        ?string $reason = null,
        ?string $notes = null
    ): Appointment {
        $result = DB::transaction(function () use (
            $appointment,
            $changedBy,
            $reason,
            $notes
        ) {
            $appointment->refresh();

            $this->ensurePermission(
                $appointment,
                $changedBy,
                'appointments.complete'
            );

            if ($appointment->status !== 'confirmed') {
                throw new RuntimeException(
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

            return $appointment->fresh([
                'business',
                'branch',
                'patientProfile',
                'doctor',
                'treatment',
                'statusHistories',
            ]);
        });

        $this->notificationService
            ->notifyAppointmentCompleted($result);

        return $result;
    }

    public function cancel(
        Appointment $appointment,
        string $cancellationReason,
        ?User $changedBy = null,
        ?string $notes = null
    ): Appointment {
        $result = DB::transaction(function () use (
            $appointment,
            $cancellationReason,
            $changedBy,
            $notes
        ) {
            $appointment->refresh();

            $this->ensurePermission(
                $appointment,
                $changedBy,
                'appointments.cancel'
            );

            if (! in_array(
                $appointment->status,
                ['pending', 'confirmed'],
                true
            )) {
                throw new RuntimeException(
                    'Yalnızca bekleyen veya onaylanmış randevular iptal edilebilir.'
                );
            }

            $cancellationReason = trim($cancellationReason);

            if ($cancellationReason === '') {
                throw new RuntimeException(
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

        $this->notificationService
            ->notifyAppointmentCancelled($result);

        return $result;
    }

    public function noShow(
        Appointment $appointment,
        ?User $changedBy = null,
        ?string $reason = null,
        ?string $notes = null
    ): Appointment {
        $result = DB::transaction(function () use (
            $appointment,
            $changedBy,
            $reason,
            $notes
        ) {
            $appointment->refresh();

            $this->ensurePermission(
                $appointment,
                $changedBy,
                'appointments.mark_no_show'
            );

            if ($appointment->status !== 'confirmed') {
                throw new RuntimeException(
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

            return $appointment->fresh([
                'business',
                'branch',
                'patientProfile',
                'doctor',
                'treatment',
                'statusHistories',
            ]);
        });

        $this->notificationService
            ->notifyAppointmentNoShow($result);

        return $result;
    }

    public function reschedule(
        Appointment $appointment,
        Carbon $newStartsAt,
        ?User $changedBy = null,
        ?string $reason = null,
        ?string $notes = null,
        bool $requireOnlineBookable = true
    ): Appointment {
        $newAppointment = DB::transaction(function () use (
            $appointment,
            $newStartsAt,
            $changedBy,
            $reason,
            $notes,
            $requireOnlineBookable
        ) {
            $appointment->refresh();

            $this->ensurePermission(
                $appointment,
                $changedBy,
                'appointments.reschedule'
            );

            if (! in_array(
                $appointment->status,
                ['pending', 'confirmed'],
                true
            )) {
                throw new RuntimeException(
                    'Yalnızca bekleyen veya onaylanmış randevuların tarihi değiştirilebilir.'
                );
            }

            $doctor = $appointment->doctor;
            $treatment = $appointment->treatment;

            if (! $doctor || ! $treatment) {
                throw new RuntimeException(
                    'Randevu tarihini değiştirmek için doktor ve tedavi bilgisi gereklidir.'
                );
            }

            $durationMinutes = $this->availabilityService
                ->getDurationMinutes(
                    $doctor,
                    $appointment->branch_id,
                    $treatment
                );

            if ($durationMinutes === null || $durationMinutes <= 0) {
                throw new RuntimeException(
                    'Randevu süresi belirlenemedi.'
                );
            }

            if (! $this->availabilityService->isAvailable(
                $doctor,
                $appointment->branch_id,
                $treatment,
                $newStartsAt,
                $appointment->id,
                $requireOnlineBookable
            )) {
                throw new RuntimeException(
                    'Seçilen yeni tarih ve saat için randevu müsait değil.'
                );
            }

            $newEndsAt = $newStartsAt
                ->copy()
                ->addMinutes($durationMinutes);

            $oldStatus = $appointment->status;

            $newAppointment = Appointment::create([
                'business_id' => $appointment->business_id,
                'branch_id' => $appointment->branch_id,
                'patient_profile_id' => $appointment->patient_profile_id,
                'doctor_id' => $appointment->doctor_id,
                'treatment_id' => $appointment->treatment_id,
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

        /*
         * Bildirim yeni randevu kaydına gider.
         *
         * Eski kayıt "rescheduled" olarak kalır,
         * takvimde aktif olan kayıt yeni randevudur.
         */
        $this->notificationService
            ->notifyAppointmentRescheduled($newAppointment);

        return $newAppointment;
    }
}