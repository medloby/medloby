<?php

namespace App\Notifications;

use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Hash;

class ClinicOwnerCredentialsNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(private string $temporaryPassword) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(User $notifiable, string $channel): bool
    {
        if (! $notifiable->temporary_password_expires_at?->isFuture()
            || ! Hash::check($this->temporaryPassword, $notifiable->password)) {
            return false;
        }

        return $notifiable->businessMemberships()
            ->where('role', 'business_owner')->where('is_active', true)
            ->with('business:id,status,is_verified')->get()
            ->contains(fn (BusinessUser $membership): bool => $notifiable->login_code === 'klinik-'.$membership->business_id
                && $membership->business?->allowsCrmAccess());
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Medloby klinik hesabınız hazır')
            ->greeting('Merhaba,')
            ->line('Klinik başvurunuz onaylandı. Aşağıdaki bilgilerle kliniğinizin Medloby hesabına giriş yapın.')
            ->line('Kullanıcı kodunuz: '.$notifiable->login_code)
            ->line('Geçici şifreniz: '.$this->temporaryPassword)
            ->line('Geçici şifre 48 saat geçerlidir. İlk girişte kendi şifrenizi belirleyeceksiniz.')
            ->action('Medloby’ye giriş yap', rtrim(config('app.url'), '/').route('package-offers', absolute: false).'#gorusmeler')
            ->line('Süre dolarsa giriş ekranındaki Şifremi unuttum bağlantısını kullanabilirsiniz.')
            ->salutation('Medloby');
    }
}
