<?php

namespace App\Console\Commands;

use App\Models\PlatformContract;
use App\Models\User;
use App\Services\PrivacyPublication;
use App\Services\PublicFileExposure;
use App\Services\PublicWebsite;
use App\Services\ReleaseConfiguration;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Vite;
use Illuminate\Foundation\ViteFonts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Throwable;

#[Signature('medloby:release-check')]
#[Description('Medloby’nin yayın öncesi teknik ayarlarını kontrol et')]
final class CheckReleaseReadiness extends Command
{
    public function handle(
        PrivacyPublication $privacy,
        Vite $vite,
        ReleaseConfiguration $release,
        PublicWebsite $website,
        PublicFileExposure $publicFiles,
    ): int {
        $databaseReady = $this->probe($release->databaseReady(...));
        $schemaReady = $databaseReady ? $this->probe(fn (): bool => $release->migrationsReady(app('migrator'))) : null;
        $administratorReady = $schemaReady ? $this->probe(fn (): bool => User::query()->useWritePdo()
            ->where('is_admin', true)->whereNotNull('email')->whereNotNull('email_verified_at')->exists()) : null;
        $contractReady = $schemaReady ? $this->probe(fn (): bool => PlatformContract::query()->useWritePdo()
            ->effectiveClinicMembership()->exists()) : null;

        $checks = [
            ['Yayın ortamı ve hata ayıklama', config('app.env') === 'production' && config('app.debug') === false,
                'APP_ENV=production ve APP_DEBUG=false ayarlarını doğrulayın.'],
            ['Site adresi', $website->hasSecureOrigin(), 'APP_URL değerine sitenin HTTPS kök adresini girin.'],
            ['Güvenli oturum çerezi', config('session.secure') === true && config('session.http_only') === true
                && in_array(config('session.same_site'), ['lax', 'strict'], true),
                'SESSION_SECURE_COOKIE ve SESSION_HTTP_ONLY ayarlarını true yapın. SameSite için lax veya strict kullanın.'],
            ['Oturum deposu', $this->probe($release->sessionStorageReady(...)),
                'SESSION_DRIVER ve seçilen oturum deposunu doğrulayın. Dosya oturumlarını web kökü dışında tutun.'],
            ['API oturumu süresi', is_int(config('sanctum.expiration')) && config('sanctum.expiration') > 0,
                'Onaylanan API oturumu süresini SANCTUM_EXPIRATION ayarına dakika olarak girin.'],
            ['Güvenilen vekil sunucular', config('trustedproxy.valid') === true,
                'MEDLOBY_TRUSTED_PROXIES değeri için yalnızca vekil sunucunun IP adreslerini veya CIDR aralıklarını kullanın.'],
            ['İstek sınırlandırma deposu', $this->probe($release->limiterReady(...)),
                'CACHE_LIMITER veya CACHE_STORE için kalıcı bir depo kullanın. Birden fazla sunucuda aynı depoyu ve ön eki kullanın.'],
            ['Zamanlayıcı ve işletim önbelleği', $this->probe($release->operationsCacheReady(...)),
                'Zamanlayıcı için kalıcı bir CACHE_STORE kullanın ve işletim göstergelerinin veritabanı önbellek ve kilit tablolarını doğrulayın.'],
            ['Uygulama anahtarı', $this->probe(fn (): bool => app(Encrypter::class) instanceof Encrypter),
                'Dağıtım ortamındaki APP_KEY değerini doğrulayın.'],
            ['Derlenmiş arayüz dosyaları', $this->probe(fn (): bool => $this->assetsReady($vite)),
                'Vite yayın derlemesini oluşturun ve yazı tipi bildirimi dahil public/build dizinini dağıtıma ekleyin.'],
            ['Özel dosya dizini', $this->probe(fn (): bool => $this->privateStorageReady($databaseReady, $publicFiles)),
                'Özel dosya izinlerini, web dizinindeki bağlantıları ve rota önbelleğini doğrulayın. Gizli dosyaları ve yedekleri web kökü dışında saklayın.'],
            ['Veritabanı bağlantısı', $databaseReady, 'Dağıtım ortamındaki veritabanı bağlantısını kontrol edin.'],
            ['Veritabanı şeması', $schemaReady, $databaseReady ? 'Bekleyen migration kayıtlarını kontrol edin.' : 'Veritabanı bağlantısı doğrulanınca kontrol edilir.'],
            ['İlk platform yöneticisi', $administratorReady, $schemaReady
                ? 'Doğrulanmış platform yöneticisi hesabını veya ilk yönetici kurulumunu kontrol edin.'
                : 'Veritabanı şeması doğrulanınca kontrol edilir.'],
            ['Yürürlükteki klinik üyelik sözleşmesi', $contractReady, $schemaReady
                ? 'Onaylı sözleşme sürümünü platform yönetim ekranından yayımlayın.'
                : 'Veritabanı şeması doğrulanınca kontrol edilir.'],
            ['İşletmeci iletişim bilgileri ve KVKK yayını', $privacy->isPublished() && $privacy->contactSettingsReady(),
                'İşletmeci unvanını, posta adresini ve KVKK yayını ayarını doğrulayın. Destek ve editör adresleri ile girilmiş KVKK e-posta ve KEP adreslerini kontrol edin.'],
            ['Politika sayfaları yayın ayarı', $privacy->policiesArePublished(),
                'Onaylı politika metinleri ve KVKK ayarları hazır olduğunda MEDLOBY_POLICIES_PUBLISHED değerini etkinleştirin.'],
            ['Arka plan kuyruğu ayarı', $this->probe($release->queueReady(...)),
                'Kuyruk bağlantısını ve veritabanı kuyruğunun gerekli iş tablosu sütunlarını doğrulayın.'],
            ['Başarısız kuyruk kayıtları', $this->probe($release->failedJobStorageReady(...)),
                'QUEUE_FAILED_DRIVER ayarını ve başarısız işleri saklayacak özel dosyayı veya veritabanı tablosunu doğrulayın.'],
            ['SMTP ve gönderen ayarı', $this->probe($release->mailReady(...)),
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

    private function assetsReady(Vite $vite): bool
    {
        $build = public_path('build');
        $assetRoot = realpath($build.'/assets');
        if ($vite->isRunningHot() || $assetRoot === false) {
            return false;
        }

        $manifest = $this->readBuildManifest($build.'/manifest.json');
        if (! isset($manifest['resources/css/app.css'], $manifest['resources/js/app.js'])) {
            return false;
        }

        foreach ($manifest as $entry) {
            foreach ([$entry['file'] ?? null, ...($entry['css'] ?? []), ...($entry['assets'] ?? [])] as $file) {
                if (! $this->buildAssetReady($assetRoot, $file)) {
                    return false;
                }
            }

            foreach ([...($entry['imports'] ?? []), ...($entry['dynamicImports'] ?? [])] as $reference) {
                if (! is_string($reference) || ! isset($manifest[$reference])) {
                    return false;
                }
            }
        }

        return $this->fontAssetsReady($build, $assetRoot);
    }

    private function readBuildManifest(string $path): ?array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $manifest = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        return is_array($manifest) ? $manifest : null;
    }

    private function fontAssetsReady(string $build, string $assetRoot): bool
    {
        $manifest = $this->readBuildManifest($build.'/fonts-manifest.json');
        if ($manifest === null) {
            return false;
        }

        $fonts = new ViteFonts;
        $fonts->ensureValidManifest($manifest);
        $preloads = $manifest['preloads'] ?? [];
        $fonts->ensureValidPreloads($preloads, false);

        $inlineStyles = $manifest['style']['inline'] ?? null;
        if ((! is_string($inlineStyles) || trim($inlineStyles) === '')
            && ! $this->buildAssetReady($assetRoot, $manifest['style']['file'] ?? null)) {
            return false;
        }

        $fontFiles = collect($manifest['families'])
            ->flatMap(fn (array $family) => array_values($family['variants'] ?? []))
            ->flatMap(fn (array $variant) => $variant['files'] ?? [])
            ->pluck('file');

        return $fontFiles->isNotEmpty() && $fontFiles->merge(collect($preloads)->pluck('file'))
            ->every(fn ($file) => $this->buildAssetReady($assetRoot, $file));
    }

    private function buildAssetReady(string $assetRoot, mixed $file): bool
    {
        if (! is_string($file) || ! str_starts_with($file, 'assets/')) {
            return false;
        }

        $path = realpath($assetRoot.DIRECTORY_SEPARATOR.substr($file, strlen('assets/')));

        return $path !== false && str_starts_with($path, $assetRoot.DIRECTORY_SEPARATOR)
            && is_file($path) && is_readable($path);
    }

    private function privateStorageReady(bool $databaseReady, PublicFileExposure $publicFiles): bool
    {
        foreach (['local', 'private'] as $disk) {
            if (Route::has('storage.'.$disk) || Route::has('storage.'.$disk.'.upload')) {
                return false;
            }
        }

        $path = config('filesystems.disks.private.root');
        if (config('filesystems.disks.private.driver') !== 'local' || ! is_string($path) || blank($path)) {
            return false;
        }

        $root = realpath($path);
        $public = realpath(public_path());
        if ($root === false || $public === false || ! is_dir($root) || ! is_readable($root) || ! is_writable($root)) {
            return false;
        }

        $protectedPaths = [
            $root, base_path('.git'), base_path('vendor'), app_path(), config_path(), database_path(),
            resource_path(), base_path('routes'), base_path('bootstrap'), app()->getCachedConfigPath(),
            storage_path('logs'), storage_path('framework'),
        ];

        $localRoot = config('filesystems.disks.local.root');
        if (config('filesystems.disks.local.driver') === 'local' && is_string($localRoot) && filled($localRoot)) {
            $protectedPaths[] = $localRoot;
        }

        if ($databaseReady && DB::connection()->getDriverName() === 'sqlite') {
            $file = DB::connection()->scalar("select file from pragma_database_list where name = 'main'", useReadPdo: false);
            if (is_string($file) && $file !== '') {
                array_push($protectedPaths, $file, $file.'-wal', $file.'-shm', $file.'-journal');
            }
        }

        return ! $publicFiles->exposes([$public], $protectedPaths, $this->hasSensitivePublicName(...));
    }

    private function hasSensitivePublicName(string $name): bool
    {
        return $name === '.git'
            || preg_match('/\A\.env(?:\.|\z)|\.(?:(?:db|sqlite3?)(?:-(?:wal|shm|journal))?|dump|sql(?:\.gz)?|log(?:\.\d+)?|pem|key|pfx|p12)\z/i', $name) === 1;
    }
}
