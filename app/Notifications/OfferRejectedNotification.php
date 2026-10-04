<?php

namespace App\Notifications;

use App\Models\Offer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OfferRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Offer $offer
    ) {
    }

    /**
     * Bildirim sadece Medloby uygulaması içinde gösterilir.
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
            'notification_type' => NotificationType::OFFER_REJECTED,
            'title' => 'Teklif reddedildi',
            'message' => 'Gönderdiğiniz teklif hasta tarafından reddedildi.',
            'offer_id' => $this->offer->id,
            'conversation_id' => $this->offer->conversation_id,
            'business_id' => $this->offer->business_id,
            'patient_profile_id' => $this->offer->patient_profile_id,
            'treatment_id' => $this->offer->treatment_id,
            'rejection_reason' => $this->offer->rejection_reason,
        ];
    }
}