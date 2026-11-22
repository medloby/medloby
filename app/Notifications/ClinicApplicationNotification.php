<?php

namespace App\Notifications;

use App\Models\Business;
use Illuminate\Notifications\Notification;

class ClinicApplicationNotification extends Notification
{
    public function __construct(
        private Business $application,
        private string $type,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        [$title, $message] = match ($this->type) {
            NotificationType::CLINIC_APPLICATION_SUBMITTED => ['Yeni klinik başvurusu', "{$this->application->name} başvurusu değerlendirme bekliyor."],
            NotificationType::CLINIC_APPLICATION_APPROVED => ['Klinik başvurunuz onaylandı', 'Başvurunuzun sonucunu hesabınızdan görüntüleyebilirsiniz.'],
            NotificationType::CLINIC_APPLICATION_REJECTED => ['Klinik başvurunuz reddedildi', 'Başvurunuzun sonucunu hesabınızdan görüntüleyebilirsiniz.'],
        };

        return [
            'notification_type' => $this->type,
            'title' => $title,
            'message' => $message,
            'clinic_application_id' => $this->application->id,
        ];
    }
}
