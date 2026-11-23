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
use App\Notifications\PatientAppointmentChangedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AppointmentNotificationService
{
    public function __construct(
        protected BusinessNotificationPreferenceService $preferenceService
    ) {}

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

        $this->notifyBusinessUsers(
            $business,
            $appointment,
            NotificationType::NEW_APPOINTMENT
        );

        $patientUser = $appointment->patientProfile?->user;
        $patientUser?->notifyNow(new NewPatientAppointmentNotification($appointment));

        DB::afterCommit(function () use ($appointment, $business, $patientUser): void {
            rescue(function () use ($appointment, $business): void {
                if (filled($business->email) && $this->preferenceService->isEmailEnabled($business, NotificationType::NEW_APPOINTMENT)) {
                    Mail::to($business->email)->queue(new NewAppointmentMail($appointment));
                }
            });

            if (filled($patientUser?->email)) {
                rescue(fn () => Mail::to($patientUser->email)->queue(new NewPatientAppointmentMail($appointment)));
            }
        });
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
     * Hasta işlemi kendisi yaptıysa ilgili şubeye de bildirim gider.
     */
    public function notifyAppointmentCancelled(
        Appointment $appointment,
        bool $byPatient = false,
    ): void {
        $this->notifyPatient(
            $appointment,
            NotificationType::APPOINTMENT_CANCELLED,
            new AppointmentCancelledNotification($appointment)
        );

        if ($byPatient) {
            $this->notifyBusinessUsers($appointment->business, $appointment, NotificationType::APPOINTMENT_CANCELLED);
        }
    }

    /**
     * Randevu yeniden planlandı.
     *
     * Hasta işlemi kendisi yaptıysa ilgili şubeye de bildirim gider.
     */
    public function notifyAppointmentRescheduled(
        Appointment $appointment,
        bool $byPatient = false,
    ): void {
        $this->notifyPatient(
            $appointment,
            NotificationType::APPOINTMENT_RESCHEDULED,
            new AppointmentRescheduledNotification($appointment)
        );

        if ($byPatient) {
            $this->notifyBusinessUsers($appointment->business, $appointment, NotificationType::APPOINTMENT_RESCHEDULED);
        }
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

        $notification = match ($notificationType) {
            NotificationType::NEW_APPOINTMENT => new NewAppointmentNotification($appointment),
            NotificationType::APPOINTMENT_CANCELLED, NotificationType::APPOINTMENT_RESCHEDULED => new PatientAppointmentChangedNotification($appointment, $notificationType),

            default => null,
        };

        if (! $notification) {
            return;
        }

        $members = $business->memberships()->activeForBranch($appointment->branch)->with('user')->get();
        foreach ($members as $membership) {
            $membership->user?->notifyNow($notification);
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

        $patientUser->notifyNow($notification);
    }
}
