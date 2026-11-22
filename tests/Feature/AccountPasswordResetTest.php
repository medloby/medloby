<?php

use App\Models\User;
use App\Notifications\AccountPasswordResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

test('an emailed link changes the password once and revokes previous access tokens', function () {
    config(['app.url' => 'https://medloby.example.test', 'mail.default' => 'array', 'queue.default' => 'sync']);
    $user = User::factory()->unverified()->create(['email' => 'klinik@example.test', 'password' => 'eski-sifre-123']);
    $previousToken = $user->createToken('previous-session');
    $this->get(route('package-offers'))->assertOk()->assertSee(route('password.request'));
    $this->get(route('password.request'))->assertOk()->assertSeeText('Yenileme bağlantısı gönder');

    $this->post(route('password.email'), ['email' => 'KLINIK@example.test'])
        ->assertRedirectToRoute('password.request');

    $mail = Mail::mailer('array')->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
    expect($mail->getTo()[0]->getAddress())->toBe('klinik@example.test');
    expect(preg_match('~href="(https://medloby\.example\.test/sifre-yenile\?[^"]+)"~', $mail->getHtmlBody(), $match))->toBe(1);
    $link = html_entity_decode($match[1]);
    parse_str(parse_url($link, PHP_URL_QUERY), $credentials);

    $this->get($link)->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertSeeText('Yeni şifre oluştur')->assertSee($credentials['token']);
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email, 'token' => $credentials['token']]);

    $newPassword = ['password' => 'yeni-sifre-123', 'password_confirmation' => 'yeni-sifre-123'];
    $this->post(route('password.update'), $credentials + ['password' => $newPassword['password'], 'password_confirmation' => 'yanlis-tekrar'])
        ->assertUnprocessable()->assertSeeText('Yazdığınız şifreler aynı olmalıdır.');
    $this->post(route('password.update'), $credentials + $newPassword)
        ->assertRedirect(route('package-offers').'#gorusmeler');
    $this->get(route('package-offers'))->assertOk()->assertSeeText('Şifreniz güncellendi.');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    $this->getJson('/api/me', ['Authorization' => 'Bearer '.$previousToken->plainTextToken])->assertUnauthorized();
    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'eski-sifre-123'])->assertUnprocessable();
    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'yeni-sifre-123'])->assertOk();
    $this->post(route('password.update'), $credentials + $newPassword)
        ->assertUnprocessable()->assertSeeText('Yeni bir bağlantı isteyin.')->assertDontSee($credentials['token']);
});

test('an unknown account and a registered account get identical recovery feedback', function () {
    Notification::fake();
    $user = User::factory()->create();

    $known = $this->post(route('password.email'), ['email' => $user->email]);
    $message = session('status');
    $known->assertRedirectToRoute('password.request');
    $this->post(route('password.email'), ['email' => 'bilinmeyen@example.test'])
        ->assertRedirectToRoute('password.request')->assertSessionHas('status', $message);
    $this->post(route('password.email'), ['email' => $user->email])
        ->assertRedirectToRoute('password.request')->assertSessionHas('status', $message);

    Notification::assertSentTimes(AccountPasswordResetNotification::class, 1);
    Notification::assertSentTo($user, AccountPasswordResetNotification::class);
});

test('an expired or mismatched link cannot change the password', function () {
    $user = User::factory()->create(['password' => 'mevcut-sifre']);
    $otherAccount = User::factory()->create();
    $token = Password::createToken($user);
    $credentials = ['token' => $token, 'password' => 'yeni-sifre-123', 'password_confirmation' => 'yeni-sifre-123'];

    $this->post(route('password.update'), $credentials + ['email' => $otherAccount->email])->assertUnprocessable();
    $this->travel(61)->minutes();
    $this->post(route('password.update'), $credentials + ['email' => $user->email])->assertUnprocessable();
    expect(Hash::check('mevcut-sifre', $user->fresh()->password))->toBeTrue();
});

test('the database queue encrypts the reset link before delivery', function () {
    config(['queue.default' => 'database', 'mail.default' => 'array']);
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email])->assertRedirectToRoute('password.request');

    $payload = json_decode(DB::table('jobs')->sole()->payload, true, flags: JSON_THROW_ON_ERROR);
    expect(decrypt($payload['data']['command']))->toContain(AccountPasswordResetNotification::class);
    expect(Mail::mailer('array')->getSymfonyTransport()->messages())->toBeEmpty();
    $this->artisan('queue:work', ['--once' => true])->assertExitCode(0);
    expect(Mail::mailer('array')->getSymfonyTransport()->messages())->toHaveCount(1);
    $this->assertDatabaseCount('jobs', 0);
});

test('login requests are limited per address and account and recovery requests have a separate limit', function () {
    $login = ['email' => 'ornek@example.test', 'password' => 'yanlis-sifre'];
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson('/api/login', $login)->assertUnprocessable();
    }

    $this->postJson('/api/login', ['email' => 'ORNEK@example.test', 'password' => 'yanlis-sifre'])->assertTooManyRequests();
    $this->postJson('/api/login', ['email' => 'baska@example.test', 'password' => 'yanlis-sifre'])->assertUnprocessable();
    $this->postJson('/api/login', ['email' => ['ornek@example.test'], 'password' => 'yanlis-sifre'])->assertUnprocessable();

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('password.email'), ['email' => 'bilinmeyen@example.test'])->assertRedirectToRoute('password.request');
    }
    $this->post(route('password.email'), ['email' => 'bilinmeyen@example.test'])->assertTooManyRequests();
});

test('patient and clinic forms share a request limit', function () {
    for ($attempt = 0; $attempt < 15; $attempt++) {
        $this->postJson('/api/register/patient', [])->assertUnprocessable();
    }

    $this->postJson('/api/register/patient', [])->assertTooManyRequests();
    $this->postJson('/api/register/business', [])->assertTooManyRequests();
});
