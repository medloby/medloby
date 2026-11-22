<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        RateLimiter::for('login', function (Request $request): array {
            $email = $request->input('email');
            $account = is_string($email) ? Str::lower(substr($email, 0, 255)) : '';

            return [
                Limit::perMinute(60)->by('ip:'.$request->ip()),
                Limit::perMinute(5)->by('account:'.$account.'|'.$request->ip()),
            ];
        });

        VerifyEmail::toMailUsing(fn (object $notifiable, string $url): MailMessage => (new MailMessage)
            ->subject('Medloby e-posta doğrulama')
            ->greeting('Merhaba,')
            ->line('Medloby hesabınız için e-posta adresinizi aşağıdaki bağlantıyla doğrulayın.')
            ->action('E-posta adresimi doğrula', $url)
            ->line('Bu işlemi siz başlatmadıysanız bu e-postayı dikkate almayın.')
            ->salutation('Medloby'));
    }
}
