<?php

use App\Models\PlatformContract;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->releaseDirectory = sys_get_temp_dir().'/medloby-release-'.Str::uuid();
    app()->usePublicPath($this->releaseDirectory.'/public');

    File::ensureDirectoryExists(public_path('build/assets'));
    File::ensureDirectoryExists($this->releaseDirectory.'/private');
    File::put(public_path('build/manifest.json'), json_encode([
        'resources/css/app.css' => ['file' => 'assets/app.css'],
        'resources/js/app.js' => ['file' => 'assets/app.js'],
    ], JSON_THROW_ON_ERROR));
    File::put(public_path('build/assets/app.css'), 'body {}');
    File::put(public_path('build/assets/app.js'), 'export {}');
    $this->fontManifest = [
        'version' => 1,
        'style' => ['file' => 'assets/fonts.css'],
        'preloads' => [['alias' => 'instrument-sans', 'file' => 'assets/instrument.woff2']],
        'families' => ['instrument-sans' => [
            'variants' => ['400 700:normal' => ['files' => [['file' => 'assets/instrument.woff2']]]],
        ]],
    ];
    File::put(public_path('build/fonts-manifest.json'), json_encode($this->fontManifest, JSON_THROW_ON_ERROR));
    File::put(public_path('build/assets/fonts.css'), '@font-face { font-family: Instrument Sans; src: url(instrument.woff2); }');
    File::put(public_path('build/assets/instrument.woff2'), 'Font fixture');

    config([
        'app.env' => 'production',
        'app.debug' => false,
        'app.url' => 'https://medloby.example.test',
        'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
        'session.secure' => true,
        'session.http_only' => true,
        'cache.limiter' => 'database',
        'filesystems.disks.private.driver' => 'local',
        'filesystems.disks.private.root' => $this->releaseDirectory.'/private',
        'queue.default' => 'database',
        'queue.connections.database.connection' => null,
        'queue.connections.database.table' => 'jobs',
        'mail.default' => 'smtp',
        'mail.mailers.smtp.scheme' => null,
        'mail.mailers.smtp.url' => null,
        'mail.mailers.smtp.host' => 'relay.example.test',
        'mail.mailers.smtp.port' => 587,
        'mail.mailers.smtp.password' => 'test-relay-password',
        'mail.from.address' => 'mailer@example.test',
        'medloby.privacy_published' => true,
        'medloby.operator.legal_name' => 'Medloby Test İşletmesi',
        'medloby.operator.postal_address' => 'Test Mahallesi',
        'medloby.operator.support_email' => 'support@example.test',
        'medloby.operator.editor_email' => 'editor@example.test',
    ]);

    $this->administrator = User::factory()->create(['is_admin' => true]);
    $this->clinicContract = PlatformContract::create([
        'contract_type' => 'clinic_membership',
        'title' => 'Test Klinik Üyelik Sözleşmesi',
        'version' => 'test',
        'content' => 'Test koşulları.',
        'is_required' => true,
        'status' => 'published',
        'published_at' => now()->subMinute(),
        'effective_at' => now()->subMinute(),
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->releaseDirectory);
});

test('release readiness succeeds with an effective deployment configuration without printing values', function () {
    expect(Artisan::call('medloby:release-check'))->toBe(Command::SUCCESS);

    expect(Artisan::output())
        ->toContain('Hazır · Veritabanı şeması', 'Hazır · İlk platform yöneticisi', 'Hazır · Yürürlükteki klinik üyelik sözleşmesi')
        ->not->toContain('Eksik ·', 'Bekliyor ·', config('app.key'), config('mail.mailers.smtp.password'), $this->administrator->email, $this->releaseDirectory);

    $this->assertDatabaseCount('jobs', 0);
});

test('release readiness reports incompatible settings and missing build files together', function () {
    config([
        'app.env' => 'local',
        'app.debug' => true,
        'app.url' => 'http://localhost',
        'app.key' => '',
        'session.secure' => false,
        'session.same_site' => 'none',
        'trustedproxy.valid' => false,
        'cache.limiter' => 'array',
        'queue.default' => 'sync',
        'mail.default' => 'log',
        'medloby.operator.editor_email' => 'invalid',
    ]);
    File::delete(public_path('build/assets/app.js'));

    expect(Artisan::call('medloby:release-check'))->toBe(Command::FAILURE);

    expect(Artisan::output())->toContain(
        'Eksik · Yayın ortamı ve hata ayıklama',
        'Eksik · Site adresi',
        'Eksik · Güvenli oturum çerezi',
        'Eksik · Güvenilen vekil sunucular',
        'Eksik · İstek sınırlandırma deposu',
        'Eksik · Uygulama anahtarı',
        'Eksik · Derlenmiş arayüz dosyaları',
        'Eksik · İşletmeci iletişim bilgileri ve KVKK yayını',
        'Eksik · Arka plan kuyruğu ayarı',
        'Eksik · SMTP ve gönderen ayarı',
        'Hazır · Veritabanı şeması',
    );
});

test('release readiness flags an invalid configured privacy channel and allows written requests by post', function (string $channel) {
    config(['medloby.operator.'.$channel => 'invalid-private-channel']);

    expect(Artisan::call('medloby:release-check'))->toBe(Command::FAILURE);
    expect(Artisan::output())
        ->toContain('Eksik · İşletmeci iletişim bilgileri ve KVKK yayını')
        ->not->toContain('invalid-private-channel');

    config(['medloby.operator.'.$channel => null]);
    expect(Artisan::call('medloby:release-check'))->toBe(Command::SUCCESS);
})->with(['privacy_email', 'privacy_kep']);

test('release readiness reports missing or invalid local font assets without exposing the file location', function (string $issue) {
    $path = public_path('build/fonts-manifest.json');
    $manifest = $this->fontManifest;
    $staleVariant = $manifest;
    $staleVariant['families']['instrument-sans']['variants']['400 700:normal']['files'] = [['file' => 'assets/previous.woff2']];

    match ($issue) {
        'missing manifest' => File::delete($path),
        'invalid manifest' => File::put($path, '{'),
        'missing stylesheet' => File::delete(public_path('build/assets/fonts.css')),
        'missing font' => File::delete(public_path('build/assets/instrument.woff2')),
        'stale preload' => File::put($path, json_encode([
            ...$manifest, 'preloads' => [['alias' => 'instrument-sans', 'file' => 'assets/previous.woff2']],
        ], JSON_THROW_ON_ERROR)),
        'stale variant' => File::put($path, json_encode($staleVariant, JSON_THROW_ON_ERROR)),
    };

    expect(Artisan::call('medloby:release-check'))->toBe(Command::FAILURE);
    expect(Artisan::output())->toContain('Eksik · Derlenmiş arayüz dosyaları', 'Hazır · Veritabanı şeması')
        ->not->toContain($this->releaseDirectory);
})->with(['missing manifest', 'invalid manifest', 'missing stylesheet', 'missing font', 'stale preload', 'stale variant']);

test('release readiness reports missing dependencies of an existing app entry', function (string $field, string $reference) {
    $path = public_path('build/manifest.json');
    $manifest = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
    $manifest['resources/js/app.js'][$field] = [$reference];
    File::put($path, json_encode($manifest, JSON_THROW_ON_ERROR));

    expect(Artisan::call('medloby:release-check'))->toBe(Command::FAILURE);
    expect(Artisan::output())->toContain('Eksik · Derlenmiş arayüz dosyaları');
})->with([
    'missing chunk' => ['imports', 'removed-chunk.js'],
    'missing dynamic chunk' => ['dynamicImports', 'removed-chunk.js'],
    'missing CSS' => ['css', 'assets/previous.css'],
    'missing asset' => ['assets', 'assets/previous.woff2'],
]);

test('release readiness defers account and contract checks when a migration is pending', function () {
    $migration = DB::table('migrations')->orderByDesc('id')->value('migration');
    DB::table('migrations')->where('migration', $migration)->delete();

    expect(Artisan::call('medloby:release-check'))->toBe(Command::FAILURE);

    expect(Artisan::output())
        ->toContain('Hazır · Veritabanı bağlantısı', 'Eksik · Veritabanı şeması', 'Bekliyor · İlk platform yöneticisi', 'Bekliyor · Yürürlükteki klinik üyelik sözleşmesi');
});

test('release readiness withholds database errors and continues checking the remaining settings', function () {
    $database = DB::getDefaultConnection();
    config([
        'database.connections.release_unavailable' => [
            'driver' => 'sqlite',
            'database' => $this->releaseDirectory.'/missing.sqlite',
            'prefix' => '',
        ],
    ]);

    DB::setDefaultConnection('release_unavailable');
    try {
        expect(Artisan::call('medloby:release-check'))->toBe(Command::FAILURE);

        expect(Artisan::output())
            ->toContain('Eksik · Veritabanı bağlantısı', 'Bekliyor · Veritabanı şeması', 'Hazır · SMTP ve gönderen ayarı')
            ->not->toContain('missing.sqlite', $this->releaseDirectory);
    } finally {
        DB::setDefaultConnection($database);
    }
});

test('release readiness requires a verified administrator and a currently effective required contract', function () {
    $this->administrator->forceFill(['email_verified_at' => null])->save();
    $this->clinicContract->update(['effective_at' => now()->addDay()]);
    PlatformContract::create([
        ...$this->clinicContract->getAttributes(),
        'version' => 'expired',
        'effective_at' => now()->subDays(2),
        'expires_at' => now()->subDay(),
    ]);

    expect(Artisan::call('medloby:release-check'))->toBe(Command::FAILURE);

    expect(Artisan::output())->toContain('Eksik · İlk platform yöneticisi', 'Eksik · Yürürlükteki klinik üyelik sözleşmesi');

    $this->getJson('/api/register/business/contract')->assertNotFound();
});

test('release readiness detects a public path to any part of private storage', function (string $exposure) {
    $folder = $this->releaseDirectory.'/private/conversations/1';
    File::ensureDirectoryExists($folder);
    File::put($folder.'/attachment.txt', 'Synthetic private attachment');

    if ($exposure === 'attachment directory') {
        File::link($folder, public_path('storage'));
    } elseif ($exposure === 'individual attachment') {
        File::ensureDirectoryExists(public_path('downloads'));
        File::link($folder.'/attachment.txt', public_path('downloads/attachment.txt'));
    } elseif ($exposure === 'link inside published storage') {
        $published = $this->releaseDirectory.'/published';
        File::ensureDirectoryExists($published);
        File::link($published, public_path('storage'));
        File::link($folder, $published.'/attachments');
    } else {
        config(['filesystems.disks.private.root' => $this->releaseDirectory]);
    }

    expect(Artisan::call('medloby:release-check'))->toBe(Command::FAILURE);
    expect(Artisan::output())->toContain('Eksik · Özel dosya dizini', 'Hazır · Derlenmiş arayüz dosyaları')
        ->not->toContain($this->releaseDirectory, 'Synthetic private attachment');
    expect(File::get($folder.'/attachment.txt'))->toBe('Synthetic private attachment');
})->with(['attachment directory', 'individual attachment', 'link inside published storage', 'public within private root']);

test('release readiness accepts separate public storage even when a link points back to public', function () {
    $published = $this->releaseDirectory.'/private-public';
    File::ensureDirectoryExists($published);
    File::link($published, public_path('storage'));
    File::link(public_path(), $published.'/back');

    expect(Artisan::call('medloby:release-check'))->toBe(Command::SUCCESS);
    expect(Artisan::output())->toContain('Hazır · Özel dosya dizini');
});

test('release readiness checks the public storage link, queue table and effective SMTP URL', function () {
    File::link($this->releaseDirectory.'/private', public_path('storage'));
    config([
        'queue.connections.database.table' => 'missing_jobs',
        'mail.mailers.smtp.host' => '',
        'mail.mailers.smtp.port' => 0,
        'mail.mailers.smtp.url' => 'smtp://test-user:test-url-password@relay.example.test:465',
    ]);

    expect(Artisan::call('medloby:release-check'))->toBe(Command::FAILURE);

    expect(Artisan::output())
        ->toContain('Eksik · Özel dosya dizini', 'Eksik · Arka plan kuyruğu ayarı', 'Hazır · SMTP ve gönderen ayarı')
        ->not->toContain('test-url-password');

    config(['mail.mailers.smtp.url' => 'smtps://relay.example.test:465']);

    expect(Artisan::call('medloby:release-check'))->toBe(Command::FAILURE);
    expect(Artisan::output())->toContain('Eksik · SMTP ve gönderen ayarı');
});
