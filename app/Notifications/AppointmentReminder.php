<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class AppointmentReminder extends Notification
{
    public function __construct(private int $appointmentId) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'notification_type' => NotificationType::APPOINTMENT_REMINDER,
            'title' => 'Randevu hatırlatması',
            'message' => 'Yaklaşan randevunuzun bilgilerini Randevularım ekranından görüntüleyebilirsiniz.',
            'appointment_id' => $this->appointmentId,
        ];
    }
}
