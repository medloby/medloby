<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ClinicApplicationRejectedNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use QueuesWithApplicationTransaction;

    public function __construct(
        private int $businessId,
        private int $reviewId,
        private ?string $applicantExplanation,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(User $notifiable, string $channel): bool
    {
        return $notifiable->clinic_onboarding_pending && filled($notifiable->email)
            && $notifiable->businessMemberships()
                ->where('business_id', $this->businessId)
                ->where('role', 'business_owner')
                ->where('is_active', true)
                ->whereHas('business', fn (Builder $business) => $business->where('status', 'rejected')->where('email', $notifiable->email)
                    ->whereHas('latestApplicationReview', fn (Builder $review) => $review
                        ->whereKey($this->reviewId)->where('decision', 'rejected')))
                ->exists();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Medloby klinik başvurunuzun sonucu')
            ->greeting('Merhaba,')
            ->line('Medloby klinik başvurunuz değerlendirme sonucunda reddedildi.')
            ->salutation('Medloby');

        if (filled($this->applicantExplanation)) {
            $mail->line($this->applicantExplanation);
        }

        return $mail;
    }
}
