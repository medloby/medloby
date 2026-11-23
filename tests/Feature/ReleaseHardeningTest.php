<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\User;
use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

afterEach(function () {
    Request::setTrustedHosts([]);
});

test('private responses prevent caching, indexing and referrer leakage', function () {
    $this->withoutVite();
    $this->get('/paket-teklifleri')->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Robots-Tag', 'noindex')
        ->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=(), payment=()')
        ->assertHeaderMissing('Strict-Transport-Security');
    $this->get('/sifre-yenile')->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Referrer-Policy', 'no-referrer');
    $this->getJson('/api/me')->assertUnauthorized()->assertHeader('Cache-Control', 'no-store, private');
    $this->postJson('/api/login')->assertUnprocessable()->assertHeader('Cache-Control', 'no-store, private');
});

test('an unverified clinic staff identity cannot enter a platform mutation', function () {
    $branch = Branch::factory()->create();
    $staff = User::factory()->unverified()->create(['is_admin' => true, 'staff_business_id' => $branch->business_id]);

    $this->actingAs($staff, 'sanctum')->postJson('/api/treatment-categories', [
        'name' => 'Yetkisiz kategori',
    ])->assertForbidden();

    $this->assertDatabaseMissing('treatment_categories', ['name' => 'Yetkisiz kategori']);
});

test('anonymous catalog requests cannot read inactive treatments or categories', function () {
    $active = TreatmentCategory::factory()->create(['is_active' => true]);
    $draft = TreatmentCategory::factory()->create(['parent_id' => $active->id, 'is_active' => false]);
    $treatment = Treatment::create([
        'treatment_category_id' => $active->id, 'name' => 'Örnek taslak tedavi', 'slug' => 'ornek-taslak-tedavi',
        'is_active' => false,
    ]);

    $this->getJson('/api/treatment-categories?active_only=0')->assertOk()->assertJsonCount(1, 'data.categories')
        ->assertJsonPath('data.categories.0.children', []);
    $this->getJson('/api/treatment-categories/'.$active->id)->assertOk()->assertJsonPath('data.treatments', []);
    $this->getJson('/api/treatment-categories/'.$draft->id)->assertNotFound();
    $this->getJson('/api/treatments?active_only=0')->assertOk()->assertJsonPath('data.treatments', []);
    $this->getJson('/api/treatments/'.$treatment->id)->assertNotFound();

    $this->actingAs(User::factory()->create(['is_admin' => true]), 'sanctum');
    $this->getJson('/api/treatment-categories/'.$draft->id)->assertOk()->assertJsonPath('data.id', $draft->id);
    $this->getJson('/api/treatments/'.$treatment->id)->assertOk()->assertJsonPath('data.id', $treatment->id);
});

test('staging hides indexing and the public sitemap follows clinic publication immediately', function () {
    $this->withoutVite();
    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /', false);
    $this->get('/sitemap.xml')->assertNotFound();

    $public = Business::factory()->create(['status' => 'active', 'is_verified' => true, 'slug' => 'acik-klinik']);
    Branch::factory()->for($public)->create(['status' => 'active']);
    foreach ([
        ['status' => 'pending', 'is_verified' => true, 'slug' => 'bekleyen-klinik'],
        ['status' => 'active', 'is_verified' => false, 'slug' => 'dogrulanmamis-klinik'],
        ['status' => 'active', 'is_verified' => true, 'slug' => 'subesi-kapali-klinik'],
    ] as $attributes) {
        $clinic = Business::factory()->create($attributes);
        Branch::factory()->for($clinic)->create(['status' => $clinic->slug === 'subesi-kapali-klinik' ? 'inactive' : 'active']);
    }

    $this->app->detectEnvironment(fn (): string => 'production');
    $host = 'https://medloby.example.test';
    config(['app.url' => $host, 'medloby.search_indexing' => true]);

    $this->get($host.'/robots.txt')->assertOk()->assertSee('Sitemap: '.$host.'/sitemap.xml', false);
    $sitemap = $this->get($host.'/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    $xml = simplexml_load_string($sitemap->streamedContent());
    expect((string) $xml->url[0]->loc)->toBe($host.'/');
    expect((string) $xml->url[1]->loc)->toBe($host.'/klinikler/acik-klinik');
    expect(count($xml->url))->toBe(2);

    $this->get($host.'/')->assertOk()->assertHeaderMissing('X-Robots-Tag')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000')
        ->assertSee('rel="canonical" href="'.$host.'/"', false);
    $this->get($host.'/?konum=ankara')->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
    $this->get($host.'/klinikler/acik-klinik')->assertOk()->assertHeaderMissing('X-Robots-Tag')
        ->assertSee('rel="canonical" href="'.$host.'/klinikler/acik-klinik"', false);
    $this->get($host.'/politikalar/kvkk')->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
    $this->get('http://medloby.example.test/')->assertOk()->assertHeaderMissing('Strict-Transport-Security');
    $this->get('https://invalid-host.example.test/')->assertBadRequest();

    $public->update(['status' => 'suspended']);
    $this->get($host.'/klinikler/acik-klinik')->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex');
    expect($this->get($host.'/sitemap.xml')->assertOk()->streamedContent())->not->toContain('acik-klinik');
});

test('an untrusted proxy header cannot reset the existing login limit', function () {
    config(['trustedproxy.proxies' => ['192.0.2.8']]);
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.9']);

    $login = ['email' => 'test-user@example.test', 'password' => 'synthetic-incorrect-password'];
    foreach (range(1, 5) as $number) {
        $this->postJson('/api/login', $login, ['X-Forwarded-For' => '198.51.100.'.$number])->assertUnprocessable();
    }
    $this->postJson('/api/login', $login, ['X-Forwarded-For' => '198.51.100.42'])
        ->assertTooManyRequests()->assertHeader('Retry-After');
});

test('database-backed limiter consumers share the same counter and expiry', function () {
    $store = Cache::store('database');
    $first = new RateLimiter($store);
    $second = new RateLimiter(Cache::store('database'));
    $first->hit('shared-limit-fixture', decaySeconds: 60);

    expect($second->tooManyAttempts('shared-limit-fixture', maxAttempts: 1))->toBeTrue();
    $this->travel(61)->seconds();
    expect($second->tooManyAttempts('shared-limit-fixture', maxAttempts: 1))->toBeFalse();
});
