<?php

namespace App\Services;

use App\Mail\NewAppointmentMail;
use App\Mail\NewPatientAppointmentMail;
use App\Models\Appointment;
use App\Notifications\AppointmentCancelledNotification;
use App\Notifications\AppointmentCompletedNotification;
use App\Notifications\AppointmentConfirmedNotification;
use App\Notifications\AppointmentNoShowNotification;
use App\Notifications\AppointmentRescheduledNotification;
use App\Notifications\NewAppointmentNotification;
use App\Notifications\NewPatientAppointmentNotification;
use App\Notifications\NotificationType;
use Illuminate\Support\Facades\Mail;

class AppointmentNotificationService
{
    public function __construct(
        protected BusinessNotificationPreferenceService $preferenceService
    ) {
    }

    /**
     * Yeni randevu:
     *
     * Klinik:
     * - Sistem içi bildirim
     * - Tercihe bağlı e-posta
     *
     * Hasta:
     * - Sistem içi bildirim
     * - E-posta
     */
    public function notifyNewAppointment(
        Appointment $appointment
    ): void {
        $appointment->loadMissing([
            'business',
            'branch',
            'patientProfile.user',
            'doctor.person',
            'treatment',
            'offer',
        ]);

        $business = $appointment->business;

        if (! $business) {
            return;
        }

        /*
         * KLİNİK
         */
        $this->notifyBusinessUsers(
            $business,
            $appointment,
            NotificationType::NEW_APPOINTMENT
        );

        if (
            $this->preferenceService->isEmailEnabled(
                $business,
                NotificationType::NEW_APPOINTMENT
            )
            && filled($business->email)
        ) {
            Mail::to($business->email)
                ->send(
                    new NewAppointmentMail($appointment)
                );
        }

        /*
         * HASTA
         */
        $patientUser = $appointment
            ->patientProfile
            ?->user;

        if (! $patientUser) {
            return;
        }

        $patientUser->notify(
            new NewPatientAppointmentNotification(
                $appointment
            )
        );

        if (filled($patientUser->email)) {
            Mail::to($patientUser->email)
                ->send(
                    new NewPatientAppointmentMail(
                        $appointment
                    )
                );
        }
    }

    /**
     * Randevu onaylandı.
     *
     * Sadece hastaya sistem içi bildirim.
     */
    public function notifyAppointmentConfirmed(
        Appointment $appointment
    ): void {
        $this->notifyPatient(
            $appointment,
            NotificationType::APPOINTMENT_CONFIRMED,
            new AppointmentConfirmedNotification($appointment)
        );
    }

    /**
     * Randevu iptal edildi.
     *
     * Sadece hastaya sistem içi bildirim.
     */
    public function notifyAppointmentCancelled(
        Appointment $appointment
    ): void {
        $this->notifyPatient(
            $appointment,
            NotificationType::APPOINTMENT_CANCELLED,
            new AppointmentCancelledNotification($appointment)
        );
    }

    /**
     * Randevu yeniden planlandı.
     *
     * Sadece hastaya sistem içi bildirim.
     */
    public function notifyAppointmentRescheduled(
        Appointment $appointment
    ): void {
        $this->notifyPatient(
            $appointment,
            NotificationType::APPOINTMENT_RESCHEDULED,
            new AppointmentRescheduledNotification($appointment)
        );
    }

    /**
     * Randevu tamamlandı.
     *
     * Sadece hastaya sistem içi bildirim.
     */
    public function notifyAppointmentCompleted(
        Appointment $appointment
    ): void {
        $this->notifyPatient(
            $appointment,
            NotificationType::APPOINTMENT_COMPLETED,
            new AppointmentCompletedNotification($appointment)
        );
    }

    /**
     * Randevu no-show olarak işaretlendi.
     *
     * Sadece hastaya sistem içi bildirim.
     */
    public function notifyAppointmentNoShow(
        Appointment $appointment
    ): void {
        $this->notifyPatient(
            $appointment,
            NotificationType::APPOINTMENT_NO_SHOW,
            new AppointmentNoShowNotification($appointment)
        );
    }

    /**
     * İşletmenin aktif kullanıcılarına sistem içi bildirim gönderir.
     */
    protected function notifyBusinessUsers(
        $business,
        Appointment $appointment,
        string $notificationType
    ): void {
        if (
            ! $this->preferenceService->isInAppEnabled(
                $business,
                $notificationType
            )
        ) {
            return;
        }

        $business->loadMissing([
            'memberships.user',
        ]);

        $notification = match ($notificationType) {
            NotificationType::NEW_APPOINTMENT =>
                new NewAppointmentNotification($appointment),

            default => null,
        };

        if (! $notification) {
            return;
        }

        foreach ($business->memberships as $membership) {
            if (
                ! $membership->is_active ||
                ! $membership->user
            ) {
                continue;
            }

            $membership->user->notify($notification);
        }
    }

    /**
     * Hastaya sistem içi bildirim gönderir.
     */
    protected function notifyPatient(
        Appointment $appointment,
        string $notificationType,
        object $notification
    ): void {
        $appointment->loadMissing([
            'patientProfile.user',
        ]);

        $patientUser = $appointment
            ->patientProfile
            ?->user;

        if (! $patientUser) {
            return;
        }

        $patientUser->notify($notification);
    }
}