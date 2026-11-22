<?php

namespace App\Notifications;

use App\Models\Offer;
use Illuminate\Notifications\Notification;

class OfferTravelPlanNotification extends Notification
{
    public function __construct(private Offer $offer) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'notification_type' => NotificationType::OFFER_TRAVEL_PLAN_UPDATED,
            'title' => 'Seyahat planınız güncellendi',
            'message' => 'Güncel bilgileri kabul ettiğiniz tekliften açabilirsiniz.',
            'conversation_id' => $this->offer->conversation_id,
            'offer_id' => $this->offer->id,
        ];
    }
}
