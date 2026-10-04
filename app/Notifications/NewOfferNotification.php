<?php

namespace App\Notifications;

use App\Models\Offer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewOfferNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Offer $offer
    ) {
    }

    /**
     * Bildirimin hangi kanallardan gönderileceğini belirler.
     */
    public function via(object $notifiable): array
    {
        return [
            'database',
        ];
    }

    /**
     * Veritabanına kaydedilecek bildirim verisi.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'new_offer',
            'title' => 'Yeni teklifiniz var',
            'message' => 'Sağlık merkezinizden yeni bir teklif aldınız.',
            'offer_id' => $this->offer->id,
            'conversation_id' => $this->offer->conversation_id,
            'business_id' => $this->offer->business_id,
            'branch_id' => $this->offer->branch_id,
            'amount' => $this->offer->amount,
            'currency' => $this->offer->currency,
            'valid_until' => $this->offer->valid_until?->toISOString(),
        ];
    }
}