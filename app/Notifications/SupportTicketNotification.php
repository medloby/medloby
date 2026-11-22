<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class SupportTicketNotification extends Notification
{
    public function __construct(private int $ticketId, private string $title, private string $message) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'notification_type' => NotificationType::SUPPORT_TICKET_UPDATED,
            'support_ticket_id' => $this->ticketId,
            'title' => $this->title,
            'message' => $this->message,
        ];
    }
}
