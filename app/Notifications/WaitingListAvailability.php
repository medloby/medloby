<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

final class WaitingListAvailability extends Notification
{
    public function __construct(private int $requestId) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'notification_type' => NotificationType::WAITING_LIST_AVAILABILITY,
            'title' => 'Randevu saati açıldı',
            'message' => 'Seçtiğiniz gün için güncel saatleri randevu ekranından kontrol edebilirsiniz.',
            'waiting_list_id' => $this->requestId,
        ];
    }
}
