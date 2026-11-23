<?php

namespace App\Console\Commands;

use App\Models\PlatformContract;
use App\Models\User;
use App\Services\PrivacyPublication;
use App\Services\ReleaseConfiguration;
use FilesystemIterator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Vite;
use Illuminate\Foundation\ViteFonts;
use Throwable;

#[Signature('medloby:release-check')]
#[Description('Medloby’nin yayın öncesi teknik ayarlarını kontrol et')]
final class CheckReleaseReadiness extends Command
{
    public function handle(PrivacyPublication $privacy, Vite $vite, ReleaseConfiguration $release): int
    {
        $databaseReady = $this->probe($release->databaseReady(...));
        $schemaReady = $databaseReady ? $this->probe(fn (): bool => $release->migrationsReady(app('migrator'))) : null;
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
                'Vite yayın derlemesini oluşturun ve yazı tipi bildirimi dahil public/build dizinini dağıtıma ekleyin.'],
            ['Özel dosya dizini', $this->probe(fn (): bool => $this->privateStorageReady()),
                'Özel dosya dizinini, dosya izinlerini ve web dizinindeki bağlantıları doğrulayın.'],
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
            ['Arka plan kuyruğu ayarı', $this->probe($release->queueReady(...)),
                'Kuyruk bağlantısını ve veritabanı kuyruğu kullanılıyorsa iş tablosunu doğrulayın.'],
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

    private function httpsUrlReady(): bool
    {
        $url = filter_var(config('app.url'), FILTER_VALIDATE_URL);

        return $url !== false && parse_url($url, PHP_URL_SCHEME) === 'https';
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

        return ! $this->publicPathsExposePrivateStorage($public, $root);
    }

    private function publicPathsExposePrivateStorage(string $public, string $private): bool
    {
        $privatePrefix = rtrim($private, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $pending = [$public];
        $visited = [];

        while ($pending !== []) {
            $path = realpath(array_pop($pending));
            if ($path === false || isset($visited[$path])) {
                continue;
            }
            $visited[$path] = true;

            $pathPrefix = rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
            if (str_starts_with($pathPrefix, $privatePrefix) || str_starts_with($privatePrefix, $pathPrefix)) {
                return true;
            }

            if (is_dir($path)) {
                foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $entry) {
                    if ($entry->isLink() || $entry->isDir()) {
                        $pending[] = $entry->getPathname();
                    }
                }
            }
        }

        return false;
    }
}
