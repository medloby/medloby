<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Password;

class AccountPasswordResetNotification extends ResetPassword implements ShouldBeEncrypted, ShouldQueue
{
    use QueuesWithApplicationTransaction;

    public function shouldSend(CanResetPassword $notifiable, string $channel): bool
    {
        return Password::tokenExists($notifiable, $this->token);
    }

    public function toMail($notifiable): MailMessage
    {
        $url = rtrim(config('app.url'), '/').route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], absolute: false);
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Medloby şifre yenileme')
            ->greeting('Merhaba,')
            ->line('Medloby hesabınız için yeni bir şifre oluşturmak üzere aşağıdaki bağlantıyı açın.')
            ->action('Şifremi yenile', $url)
            ->line("Bağlantıyı şifre yenileme isteğinizden itibaren {$minutes} dakika içinde kullanın. Bağlantı tek kullanımlıktır.")
            ->line('Bu isteği siz göndermediyseniz bu e-postayı dikkate almayın.')
            ->salutation('Medloby');
    }
}
