<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

uses(RefreshDatabase::class);

function bootstrapAdministrator(TestCase $test, string $email, string $password, string $confirmation): PendingCommand
{
    return $test->artisan('medloby:admin:bootstrap')
        ->expectsQuestion('Yöneticinin adı', 'Deneme Yöneticisi')
        ->expectsQuestion('Yöneticinin e-posta adresi', $email)
        ->expectsQuestion('Yönetici şifresi', $password)
        ->expectsQuestion('Yönetici şifresini tekrar yazın', $confirmation);
}

test('the first operator-created administrator can sign in and use the protected platform API', function () {
    $password = ' yönetici-deneme-şifresi ';

    bootstrapAdministrator($this, ' Admin@Example.test ', $password, $password)
        ->expectsOutput('Platform yöneticisi oluşturuldu.')->assertSuccessful();

    $administrator = User::query()->where('email', 'admin@example.test')->sole();
    expect($administrator->is_admin)->toBeTrue()->and($administrator->hasVerifiedEmail())->toBeTrue();
    expect(Hash::check($password, $administrator->password))->toBeTrue();
    $token = $this->postJson('/api/login', ['email' => $administrator->email, 'password' => $password])
        ->assertOk()->json('data.token');
    $this->withToken($token)->getJson('/api/admin/businesses/directory')->assertOk();

    $this->artisan('medloby:admin:bootstrap')
        ->expectsOutput('İlk platform yöneticisi zaten kurulmuş.')->assertFailed();
    $this->assertDatabaseCount('users', 1);
});

test('bootstrap refuses a noninteractive invocation', function () {
    $this->artisan('medloby:admin:bootstrap', ['--no-interaction' => true])
        ->expectsOutput('Yönetici kurulumu için etkileşimli bir terminal kullanın.')->assertFailed();

    $this->assertDatabaseCount('users', 0);
});

test('bootstrap cannot promote an existing account with the same email', function () {
    $existing = User::factory()->create(['email' => 'Admin@Example.test']);

    bootstrapAdministrator($this, 'admin@example.test', 'yonetici-deneme-sifresi', 'yonetici-deneme-sifresi')
        ->expectsOutput('Bu e-posta adresi zaten kullanımda.')->assertFailed();

    expect($existing->refresh()->is_admin)->toBeFalse();
    $this->assertDatabaseCount('users', 1);
});

test('bootstrap validates the hidden password and its confirmation', function (string $password, string $confirmation, string $message) {
    bootstrapAdministrator($this, 'admin@example.test', $password, $confirmation)
        ->expectsOutput($message)->assertFailed();

    $this->assertDatabaseCount('users', 0);
})->with([
    'mismatched' => ['yonetici-deneme-sifresi', 'baska-deneme-sifresi', 'Yazdığınız şifreler aynı olmalıdır.'],
    'too long' => [str_repeat('ş', 37), str_repeat('ş', 37), 'Şifre çok uzun veya geçersiz bir karakter içeriyor.'],
]);

test('bootstrap cannot start a second creation while the shared database lock is held', function () {
    $lock = Cache::store('database')->lock('medloby:bootstrap-administrator', 60);
    expect($lock->get())->toBeTrue();

    try {
        bootstrapAdministrator($this, 'admin@example.test', 'yonetici-deneme-sifresi', 'yonetici-deneme-sifresi')
            ->expectsOutput('Başka bir yönetici kurulumu çalışıyor.')->assertFailed();
    } finally {
        $lock->release();
    }

    $this->assertDatabaseCount('users', 0);
});
