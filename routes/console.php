<?php

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
})->purpose('SQLite ve yerel özel dosyaların yedeğini oluştur');

Artisan::command('medloby:restore {backup} {destination}', function (LocalBackup $localBackup, string $backup, string $destination): int {
    try {
        $path = $localBackup->restore($backup, $destination);
    } catch (RuntimeException $error) {
        $this->error($error->getMessage());

        return Command::FAILURE;
    }

    $this->info('Yedek yeni dizine geri yüklendi ve doğrulandı. '.$path);

    return Command::SUCCESS;
})->purpose('Yerel yedeği yeni ve boş bir dizine geri yükle');
