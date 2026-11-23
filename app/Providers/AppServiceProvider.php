<?php

namespace App\Providers;

use App\Services\PlatformOperationActivity;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
    public function boot(PlatformOperationActivity $activity): void
    {
        Event::listen(DiagnosingHealth::class, function (): void {
            DB::connection()->selectOne('select 1', useReadPdo: false);
        });

        Event::listen(JobProcessed::class, function (JobProcessed $event) use ($activity): void {
            $driver = config('queue.connections.'.$event->connectionName.'.driver');
            if (in_array($driver, ['database', 'redis', 'sqs', 'beanstalkd'], true)
                && ! $event->job->isReleased() && ! $event->job->hasFailed()) {
                $activity->record(PlatformOperationActivity::QUEUE_WORKER);
            }
        });

        Event::listen(MessageSent::class, function (MessageSent $event) use ($activity): void {
            $mailer = $event->data['mailer'] ?? null;
            if (is_string($mailer) && config('mail.mailers.'.$mailer.'.transport') === 'smtp') {
                $activity->record(PlatformOperationActivity::SMTP);
            }
        });

        RateLimiter::for('login', function (Request $request): array {
            $email = $request->input('identifier', $request->input('email'));
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
