<?php

use App\Services\LocalBackup;
use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

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
