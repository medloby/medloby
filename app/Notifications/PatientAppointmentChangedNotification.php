<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Notifications\Notification;

class PatientAppointmentChangedNotification extends Notification
{
    public function __construct(private Appointment $appointment, private string $type) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'notification_type' => $this->type,
            'title' => NotificationType::labels()[$this->type],
            'message' => $this->type === NotificationType::APPOINTMENT_CANCELLED
                ? 'Hasta randevusunu iptal etti.'
                : 'Hasta randevusunun tarihini veya saatini değiştirdi.',
            'appointment_id' => $this->appointment->id,
            'business_id' => $this->appointment->business_id,
            'branch_id' => $this->appointment->branch_id,
        ];
    }
}
