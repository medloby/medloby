<?php

use App\Models\Offer;
use App\Services\AppointmentReminderService;
use App\Services\AppointmentWaitingList;
use App\Services\CrmFollowUpReminderService;
use App\Services\LocalBackup;
use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('medloby:remind-crm-follow-ups', function (CrmFollowUpReminderService $reminders): int {
    $this->info('Hatırlatma oluşturulan takip sayısı · '.$reminders->sendDue());

    return Command::SUCCESS;
})->purpose('Saati gelen CRM takipleri için uygulama içi bildirim oluştur');

Schedule::command('medloby:remind-crm-follow-ups')->everyMinute()->withoutOverlapping(5);

Artisan::command('medloby:remind-appointments', function (AppointmentReminderService $reminders): int {
    $this->info('Hatırlatma oluşturulan randevu sayısı · '.$reminders->sendDue());

    return Command::SUCCESS;
})->purpose('Yaklaşan onaylı randevular için hastaya uygulama içi hatırlatma oluştur');

Schedule::command('medloby:remind-appointments')->everyMinute()->withoutOverlapping(5);

Artisan::command('medloby:notify-waiting-list', function (AppointmentWaitingList $waitingList): int {
    $this->info('Gönderilen uygunluk bildirimi · '.$waitingList->sendAvailable());

    return Command::SUCCESS;
})->purpose('Bekleme listesindeki hastalara uygun saatleri bildir');

Schedule::command('medloby:notify-waiting-list')->everyFiveMinutes()->withoutOverlapping(10);

Artisan::command('medloby:expire-offers', function (): int {
    $expired = Offer::query()->where('status', 'pending')->where('valid_until', '<', now()->startOfSecond())
        ->update(['status' => 'expired']);
    $this->info('Süresi dolan teklif sayısı · '.$expired);

    return Command::SUCCESS;
})->purpose('Süresi geçen ve yanıt bekleyen teklifleri kapat');

Schedule::command('medloby:expire-offers')->everyMinute()->withoutOverlapping(5);

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('medloby:backup {destination}', function (LocalBackup $backup, string $destination): int {
    try {
        $path = $backup->create($destination);
    } catch (RuntimeException $error) {
        $this->error($error->getMessage());

        return Command::FAILURE;
    }

    $this->info('Yedek doğrulandı ve kaydedildi. '.$path);

    return Command::SUCCESS;
})->purpose('SQLite veya PostgreSQL veritabanı ile yerel özel dosyaların yedeğini oluştur');

Artisan::command('medloby:restore {backup} {destination}', function (LocalBackup $localBackup, string $backup, string $destination): int {
    try {
        $path = $localBackup->restore($backup, $destination);
    } catch (RuntimeException $error) {
        $this->error($error->getMessage());

        return Command::FAILURE;
    }

    $this->info('Yedek dosyaları yeni dizine çıkarıldı ve doğrulandı. '.$path);

    return Command::SUCCESS;
})->purpose('Yedeği doğrula ve dosyaları yeni bir dizine çıkar');
