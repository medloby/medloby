<?php

namespace App\Console\Commands;

use App\Models\PlatformContract;
use App\Models\User;
use App\Services\PrivacyPublication;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Vite;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

#[Signature('medloby:release-check')]
#[Description('Medloby’nin yayın öncesi teknik ayarlarını kontrol et')]
final class CheckReleaseReadiness extends Command
{
    public function handle(PrivacyPublication $privacy, Vite $vite): int
    {
        $databaseReady = $this->probe(fn (): bool => DB::connection()->selectOne('select 1', useReadPdo: false) !== null);
        $schemaReady = $databaseReady ? $this->probe(fn (): bool => $this->migrationsReady(app('migrator'))) : null;
        $administratorReady = $schemaReady ? $this->probe(fn (): bool => User::query()->useWritePdo()
            ->where('is_admin', true)->whereNotNull('email')->whereNotNull('email_verified_at')->exists()) : null;
        $contractReady = $schemaReady ? $this->probe(fn (): bool => PlatformContract::query()->useWritePdo()
            ->effectiveClinicMembership()->exists()) : null;

        $checks = [
            ['Yayın ortamı ve hata ayıklama', config('app.env') === 'production' && config('app.debug') === false,
                'APP_ENV=production ve APP_DEBUG=false ayarlarını doğrulayın.'],
            ['Site adresi', $this->httpsUrlReady(), 'APP_URL değerine sitenin HTTPS adresini girin.'],
            ['Güvenli oturum çerezi', config('session.secure') === true && config('session.http_only') === true,
                'SESSION_SECURE_COOKIE ve SESSION_HTTP_ONLY ayarlarını true yapın.'],
            ['Uygulama anahtarı', $this->probe(fn (): bool => app(Encrypter::class) instanceof Encrypter),
                'Dağıtım ortamındaki APP_KEY değerini doğrulayın.'],
            ['Derlenmiş arayüz dosyaları', $this->probe(fn (): bool => $this->assetsReady($vite)),
                'Vite yayın derlemesini oluşturun ve public/build dizinini dağıtıma ekleyin.'],
            ['Özel dosya dizini', $this->privateStorageReady(), 'Özel dosya dizininin konumunu ve dosya izinlerini doğrulayın.'],
            ['Veritabanı bağlantısı', $databaseReady, 'Dağıtım ortamındaki veritabanı bağlantısını kontrol edin.'],
            ['Veritabanı şeması', $schemaReady, $databaseReady ? 'Bekleyen migration kayıtlarını kontrol edin.' : 'Veritabanı bağlantısı doğrulanınca kontrol edilir.'],
            ['İlk platform yöneticisi', $administratorReady, $schemaReady
                ? 'Doğrulanmış platform yöneticisi hesabını veya ilk yönetici kurulumunu kontrol edin.'
                : 'Veritabanı şeması doğrulanınca kontrol edilir.'],
            ['Yürürlükteki klinik üyelik sözleşmesi', $contractReady, $schemaReady
                ? 'Onaylı sözleşme sürümünü platform yönetim ekranından yayımlayın.'
                : 'Veritabanı şeması doğrulanınca kontrol edilir.'],
            ['İşletmeci iletişim bilgileri ve KVKK yayını', $privacy->isPublished()
                && $privacy->email('support_email') !== null && $privacy->email('editor_email') !== null,
                'Onaylı işletmeci bilgilerini, iletişim adreslerini ve KVKK yayını ayarını doğrulayın.'],
            ['Arka plan kuyruğu ayarı', $this->probe(fn (): bool => $this->queueReady()),
                'Kuyruk bağlantısını ve veritabanı kuyruğu kullanılıyorsa iş tablosunu doğrulayın.'],
            ['SMTP ve gönderen ayarı', $this->probe(fn (): bool => $this->mailReady()),
                'SMTP sunucusunu, portunu ve yetkili gönderen adresini ayarlayın.'],
        ];

        $ready = true;
        foreach ($checks as [$name, $passed, $hint]) {
            if ($passed === true) {
                $this->info('Hazır · '.$name);

                continue;
            }

            $ready = false;
            $this->line(($passed === null ? 'Bekliyor' : 'Eksik').' · '.$name.' · '.$hint);
        }

        return $ready ? self::SUCCESS : self::FAILURE;
    }

    private function probe(callable $check): bool
    {
        try {
            return $check();
        } catch (Throwable) {
            return false;
        }
    }

    private function httpsUrlReady(): bool
    {
        $url = filter_var(config('app.url'), FILTER_VALIDATE_URL);

        return $url !== false && parse_url($url, PHP_URL_SCHEME) === 'https';
    }

    private function assetsReady(Vite $vite): bool
    {
        $build = public_path('build');
        $manifestPath = $build.'/manifest.json';
        if ($vite->isRunningHot() || ! is_file($manifestPath) || ! is_readable($manifestPath)) {
            return false;
        }

        $manifest = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        if (! isset($manifest['resources/css/app.css'], $manifest['resources/js/app.js'])) {
            return false;
        }

        foreach ($manifest as $entry) {
            $file = $entry['file'] ?? null;
            if (! is_string($file) || ! str_starts_with($file, 'assets/') || ! is_file($build.'/'.$file) || ! is_readable($build.'/'.$file)) {
                return false;
            }
        }

        return true;
    }

    private function privateStorageReady(): bool
    {
        $path = config('filesystems.disks.private.root');
        if (config('filesystems.disks.private.driver') !== 'local' || ! is_string($path) || blank($path)) {
            return false;
        }

        $root = realpath($path);
        $public = realpath(public_path());
        if ($root === false || $public === false || ! is_dir($root) || ! is_readable($root) || ! is_writable($root)) {
            return false;
        }

        foreach ([$public, realpath(public_path('storage'))] as $published) {
            if ($published !== false && ($published === $root || str_starts_with($root, $published.DIRECTORY_SEPARATOR))) {
                return false;
            }
        }

        return true;
    }

    private function migrationsReady(Migrator $migrator): bool
    {
        if (! $migrator->repositoryExists()) {
            return false;
        }

        $files = $migrator->getMigrationFiles([...$migrator->paths(), database_path('migrations')]);

        return array_diff(array_keys($files), $migrator->getRepository()->getRan()) === [];
    }

    private function queueReady(): bool
    {
        $queue = config('queue.connections.'.config('queue.default'), []);
        $driver = $queue['driver'] ?? null;
        if (! in_array($driver, ['database', 'redis', 'sqs', 'beanstalkd'], true)) {
            return false;
        }

        return $driver !== 'database' || Schema::connection($queue['connection'] ?? config('database.default'))
            ->hasTable($queue['table'] ?? 'jobs');
    }

    private function mailReady(): bool
    {
        $mailer = config('mail.mailers.'.config('mail.default'), []);
        if (($mailer['transport'] ?? null) !== 'smtp' || ! filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (filled($mailer['url'] ?? null)) {
            $mailer = (new ConfigurationUrlParser)->parseConfiguration($mailer);
            if (($mailer['driver'] ?? null) !== 'smtp') {
                return false;
            }
        }

        return in_array($mailer['scheme'] ?? null, [null, 'smtp', 'smtps'], true)
            && filled($mailer['host'] ?? null)
            && filter_var($mailer['port'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) !== false;
    }
}
