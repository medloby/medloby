<?php

use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

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

test('can register a patient', function () {
    $response = $this->postJson(
        '/api/register/patient',
        validPatientRegistrationPayload()
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