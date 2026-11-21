<?php

namespace App\Notifications;

use App\Models\Conversation;
use Illuminate\Notifications\Notification;

class PackageConversationNotification extends Notification
{
    public function __construct(
        private Conversation $conversation,
        private string $type,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        [$title, $message] = $this->type === NotificationType::PATIENT_REQUEST
            ? ['Yeni paket talebi', 'Bir hasta tedavi paketi için görüşme başlattı.']
            : ['Yeni görüşme mesajı', 'Paket görüşmenize yeni bir mesaj geldi.'];

        return [
            'notification_type' => $this->type,
            'title' => $title,
            'message' => $message,
            'conversation_id' => $this->conversation->id,
        ];
    }
}
