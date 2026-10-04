<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AppointmentRescheduledNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Appointment $appointment
    ) {
    }

    /**
     * Bildirim sadece Medloby uygulama içinde gösterilir.
     */
    public function via(object $notifiable): array
    {
        return [
            'database',
        ];
    }

    /**
     * Veritabanında saklanacak bildirim verisi.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'notification_type' => NotificationType::APPOINTMENT_RESCHEDULED,
            'title' => 'Randevu tarihi değiştirildi',
            'message' => 'Randevunuzun tarihi veya saati değiştirildi.',
            'appointment_id' => $this->appointment->id,
            'business_id' => $this->appointment->business_id,
            'branch_id' => $this->appointment->branch_id,
            'patient_profile_id' => $this->appointment->patient_profile_id,
            'doctor_id' => $this->appointment->doctor_id,
            'treatment_id' => $this->appointment->treatment_id,
            'starts_at' => $this->appointment->starts_at?->toISOString(),
            'ends_at' => $this->appointment->ends_at?->toISOString(),
            'rescheduled_from_appointment_id' =>
                $this->appointment->rescheduled_from_appointment_id,
        ];
    }
}