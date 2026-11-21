<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        VerifyEmail::toMailUsing(fn (object $notifiable, string $url): MailMessage => (new MailMessage)
            ->subject('Medloby e-posta doğrulama')
            ->greeting('Merhaba,')
            ->line('Medloby hesabınız için e-posta adresinizi aşağıdaki bağlantıyla doğrulayın.')
            ->action('E-posta adresimi doğrula', $url)
            ->line('Bu işlemi siz başlatmadıysanız bu e-postayı dikkate almayın.')
            ->salutation('Medloby'));
    }
}
