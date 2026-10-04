<?php

namespace App\Notifications;

use App\Models\Offer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OfferAcceptedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Offer $offer
    ) {
    }

    /**
     * Bildirimin gönderileceği kanallar.
     *
     * Teklif kabul bildirimini şimdilik
     * sadece Medloby uygulama içi bildirim
     * olarak gönderiyoruz.
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
            'notification_type' => NotificationType::OFFER_ACCEPTED,
            'title' => 'Teklif kabul edildi',
            'message' => 'Gönderdiğiniz teklif hasta tarafından kabul edildi.',
            'offer_id' => $this->offer->id,
            'conversation_id' => $this->offer->conversation_id,
            'business_id' => $this->offer->business_id,
            'patient_profile_id' => $this->offer->patient_profile_id,
            'treatment_id' => $this->offer->treatment_id,
        ];
    }
}