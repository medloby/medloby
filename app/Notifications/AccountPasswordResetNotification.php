<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class AccountPasswordResetNotification extends ResetPassword implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

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
            ->line("Bu bağlantı {$minutes} dakika boyunca geçerlidir ve bir kez kullanılabilir.")
            ->line('Bu isteği siz göndermediyseniz bu e-postayı dikkate almayın.')
            ->salutation('Medloby');
    }
}
