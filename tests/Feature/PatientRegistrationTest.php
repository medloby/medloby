<?php

use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\Mailer\Exception\TransportException;

uses(RefreshDatabase::class);

function validPatientRegistrationPayload(): array
{
    return [
        'first_name' => 'Test',
        'last_name' => 'Hasta',
        'email' => 'patient@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'phone' => '05550000000',
        'birth_date' => '1990-01-15',
        'gender' => 'female',
        'country_code' => 'TR',
        'city' => 'İstanbul',
        'preferred_language' => 'tr',
        'preferred_currency' => 'TRY',
    ];
}

test('can register a patient', function (string $password) {
    $response = $this->postJson(
        '/api/register/patient',
        array_replace(validPatientRegistrationPayload(), [
            'password' => $password,
            'password_confirmation' => $password,
        ])
    );

    $response
        ->assertCreated()
        ->assertJson([
            'success' => true,
            'message' => 'Hasta hesabı başarıyla oluşturuldu.',
            'data' => [
                'token_type' => 'Bearer',
            ],
        ]);

    $this->assertDatabaseHas('users', [
        'name' => 'Test Hasta',
        'email' => 'patient@example.com',
    ]);

    $user = User::where(
        'email',
        'patient@example.com'
    )->firstOrFail();

    $this->assertDatabaseHas('patient_profiles', [
        'user_id' => $user->id,
        'first_name' => 'Test',
        'last_name' => 'Hasta',
        'phone' => '05550000000',
        'gender' => 'female',
        'country_code' => 'TR',
        'city' => 'İstanbul',
        'preferred_language' => 'tr',
        'preferred_currency' => 'TRY',
        'status' => 'active',
    ]);

    expect(
        PersonalAccessToken::where(
            'tokenable_id',
            $user->id
        )->count()
    )->toBe(1);
})->with([
    'typical password' => ['password123'],
    'exactly 72 bytes' => [str_repeat('ş', 36)],
]);

test('patient registration rejects invalid passwords before creating an account', function (string $password) {
    $payload = array_replace(validPatientRegistrationPayload(), [
        'password' => $password,
        'password_confirmation' => $password,
    ]);

    $this->postJson('/api/register/patient', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
})->with([
    'fewer than eight characters' => [str_repeat('ş', 7)],
    'more than 72 ASCII bytes' => [str_repeat('a', 73)],
    'more than 72 UTF-8 bytes' => [str_repeat('ş', 37)],
    'null byte' => ["ornek\0sifre"],
]);

test('a registered patient can verify their account after the queue retries a failed email', function () {
    config(['queue.default' => 'database', 'mail.default' => 'array']);
    Exceptions::fake();
    $offline = true;
    Event::listen(MessageSending::class, function () use (&$offline): void {
        if ($offline) {
            throw new TransportException('Mail transport unavailable');
        }
    });

    $payload = validPatientRegistrationPayload();
    $userId = $this->postJson('/api/register/patient', $payload)->assertCreated()->json('data.user.id');
    $this->assertDatabaseHas('users', ['id' => $userId, 'email_verified_at' => null]);
    $this->assertDatabaseHas('patient_profiles', ['user_id' => $userId]);
    $this->assertDatabaseCount('jobs', 1);
    expect(DB::table('jobs')->sole()->payload)->not->toContain($payload['email']);

    $workerOptions = ['--once' => true, '--tries' => 2, '--backoff' => 0];
    $this->artisan('queue:work', $workerOptions)->assertSuccessful();
    Exceptions::assertReported(TransportException::class);
    $this->assertDatabaseHas('jobs', ['attempts' => 1]);

    $offline = false;
    $this->artisan('queue:work', $workerOptions)->assertSuccessful();
    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('failed_jobs', 0);
    $mail = Mail::mailer('array')->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
    expect($mail->getTo()[0]->getAddress())->toBe($payload['email']);
    expect(preg_match('~href="([^"]*/email/verify/[^"]+)"~', $mail->getHtmlBody(), $match))->toBe(1);
    $this->getJson(html_entity_decode($match[1]))->assertOk();
    expect(User::findOrFail($userId)->hasVerifiedEmail())->toBeTrue();
});

test('an https verification link works through the configured proxy', function () {
    expect(config('trustedproxy'))->toHaveKey('proxies');
    config(['trustedproxy.proxies' => '192.0.2.8']);
    $user = User::factory()->unverified()->create();
    URL::useOrigin('https://medloby.example.test');
    URL::forceScheme('https');
    $link = URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);
    $upstreamUrl = 'http://'.substr($link, strlen('https://'));
    $headers = ['X-Forwarded-Proto' => 'https'];

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.9'])->getJson($upstreamUrl, $headers)->assertForbidden();
    expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.8'])->getJson($upstreamUrl, $headers)->assertOk();
    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
});

test('registration succeeds during a synchronous mail outage and resend reports that it can be retried', function () {
    config(['queue.default' => 'sync', 'mail.default' => 'array']);
    Exceptions::fake();
    Event::listen(MessageSending::class, function (): never {
        throw new TransportException('Mail transport unavailable');
    });

    $registration = $this->postJson('/api/register/patient', validPatientRegistrationPayload())->assertCreated();
    $this->assertDatabaseHas('patient_profiles', ['user_id' => $registration->json('data.user.id')]);
    $this->withToken($registration->json('data.token'))->postJson('/api/email/verification-notification')
        ->assertServiceUnavailable()->assertJsonPath('success', false);
    Exceptions::assertReportedCount(2);
});

test('patient registration validates required fields', function () {
    $response = $this->postJson(
        '/api/register/patient',
        []
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'first_name',
            'last_name',
            'email',
            'password',
        ]);
});

test('patient registration validates email format', function () {
    $payload = validPatientRegistrationPayload();
    $payload['email'] = 'not-an-email';

    $response = $this->postJson(
        '/api/register/patient',
        $payload
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'email',
        ]);
});

test('patient registration rejects duplicate email', function () {
    User::factory()->create([
        'email' => 'patient@example.com',
    ]);

    $response = $this->postJson(
        '/api/register/patient',
        validPatientRegistrationPayload()
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'email',
        ]);

    $this->postJson('/api/register/patient', array_replace(validPatientRegistrationPayload(), [
        'email' => 'PATIENT@EXAMPLE.COM',
    ]))->assertUnprocessable()->assertJsonValidationErrors('email');
});

test('patient registration requires password confirmation', function () {
    $payload = validPatientRegistrationPayload();
    unset($payload['password_confirmation']);

    $response = $this->postJson(
        '/api/register/patient',
        $payload
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'password',
        ]);
});

test('patient registration rejects weak password', function () {
    $payload = validPatientRegistrationPayload();
    $payload['password'] = '123';
    $payload['password_confirmation'] = '123';

    $response = $this->postJson(
        '/api/register/patient',
        $payload
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'password',
        ]);

    expect(User::where(
        'email',
        'patient@example.com'
    )->exists())->toBeFalse();
});

test('patient registration stores lowercase email', function () {
    $payload = validPatientRegistrationPayload();
    $payload['email'] = 'PATIENT@EXAMPLE.COM';

    $response = $this->postJson(
        '/api/register/patient',
        $payload
    );

    $response->assertCreated();

    $this->assertDatabaseHas('users', [
        'email' => 'patient@example.com',
    ]);
});

test('patient registration applies default profile values', function () {
    $payload = validPatientRegistrationPayload();

    unset(
        $payload['country_code'],
        $payload['preferred_language'],
        $payload['preferred_currency'],
        $payload['phone'],
        $payload['birth_date'],
        $payload['gender'],
        $payload['city']
    );

    $response = $this->postJson(
        '/api/register/patient',
        $payload
    );

    $response->assertCreated();

    $user = User::where(
        'email',
        'patient@example.com'
    )->firstOrFail();

    $this->assertDatabaseHas('patient_profiles', [
        'user_id' => $user->id,
        'country_code' => 'TR',
        'preferred_language' => 'tr',
        'preferred_currency' => 'TRY',
        'status' => 'active',
    ]);
});

test('patient registration normalizes country language and currency values', function () {
    $payload = validPatientRegistrationPayload();

    $payload['country_code'] = 'us';
    $payload['preferred_language'] = 'EN';
    $payload['preferred_currency'] = 'usd';

    $response = $this->postJson(
        '/api/register/patient',
        $payload
    );

    $response->assertCreated();

    $user = User::where(
        'email',
        'patient@example.com'
    )->firstOrFail();

    $this->assertDatabaseHas('patient_profiles', [
        'user_id' => $user->id,
        'country_code' => 'US',
        'preferred_language' => 'en',
        'preferred_currency' => 'USD',
    ]);
});

test('patient registration rejects future birth date', function () {
    $payload = validPatientRegistrationPayload();
    $payload['birth_date'] = now()
        ->addDay()
        ->format('Y-m-d');

    $response = $this->postJson(
        '/api/register/patient',
        $payload
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'birth_date',
        ]);

    expect(User::where(
        'email',
        'patient@example.com'
    )->exists())->toBeFalse();
});

test('patient registration hashes password', function () {
    $response = $this->postJson(
        '/api/register/patient',
        validPatientRegistrationPayload()
    );

    $response->assertCreated();

    $user = User::where(
        'email',
        'patient@example.com'
    )->firstOrFail();

    expect($user->password)
        ->not->toBe('password123')
        ->and(Hash::check(
            'password123',
            $user->password
        ))->toBeTrue();
});

test('patient registration creates only one patient profile for the new user', function () {
    $response = $this->postJson(
        '/api/register/patient',
        validPatientRegistrationPayload()
    );

    $response->assertCreated();

    $user = User::where(
        'email',
        'patient@example.com'
    )->firstOrFail();

    expect(
        PatientProfile::where(
            'user_id',
            $user->id
        )->count()
    )->toBe(1);
});

test('patient registration does not create records when validation fails', function () {
    $payload = validPatientRegistrationPayload();
    $payload['email'] = 'invalid-email';

    $response = $this->postJson(
        '/api/register/patient',
        $payload
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'email',
        ]);

    expect(User::where(
        'email',
        'patient@example.com'
    )->exists())->toBeFalse();

    expect(
        PatientProfile::query()->exists()
    )->toBeFalse();
});
