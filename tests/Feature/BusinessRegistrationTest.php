<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\ClinicContractAcceptance;
use App\Models\PlatformContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function createPublishedClinicContract(): PlatformContract
{
    return PlatformContract::create([
        'contract_type' => 'clinic_membership',
        'title' => 'Klinik Üyelik Sözleşmesi',
        'version' => '1.0',
        'content' => 'Test sözleşme içeriği.',
        'status' => 'published',
        'is_required' => true,
        'published_at' => now(),
        'effective_at' => now()->subMinute(),
        'expires_at' => null,
    ]);
}

function validBusinessRegistrationPayload(): array
{
    return [
        'owner_name' => 'Test Klinik Sahibi',
        'owner_email' => 'owner@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',

        'business_name' => 'Test Diş Kliniği',
        'business_type' => 'clinic',
        'description' => 'Test kliniği.',
        'business_email' => 'clinic@example.com',
        'business_phone' => '05550000000',
        'website' => 'https://example.com',

        'country_code' => 'TR',
        'city' => 'İstanbul',
        'district' => 'Kadıköy',
        'address' => 'Test Mahallesi Test Sokak No:1',
        'postal_code' => '34000',

        'branch_name' => 'Test Diş Kliniği Merkez',

        'contract_accepted' => true,
    ];
}

test('can register a business with accepted clinic contract', function () {
    $contract = createPublishedClinicContract();

    $response = $this->postJson(
        '/api/register/business',
        validBusinessRegistrationPayload()
    );

    $response
        ->assertCreated()
        ->assertJson([
            'success' => true,
            'data' => [
                'token_type' => 'Bearer',
                'contract' => [
                    'id' => $contract->id,
                    'title' => 'Klinik Üyelik Sözleşmesi',
                    'version' => '1.0',
                    'accepted' => true,
                ],
                'approval_status' => 'pending',
            ],
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'owner@example.com',
        'name' => 'Test Klinik Sahibi',
    ]);

    $this->assertDatabaseHas('businesses', [
        'name' => 'Test Diş Kliniği',
        'status' => 'pending',
        'is_verified' => false,
    ]);

    $business = Business::where(
        'name',
        'Test Diş Kliniği'
    )->firstOrFail();

    $this->assertDatabaseHas('branches', [
        'business_id' => $business->id,
        'name' => 'Test Diş Kliniği Merkez',
        'status' => 'pending',
    ]);

    $user = User::where(
        'email',
        'owner@example.com'
    )->firstOrFail();

    $this->assertDatabaseHas('business_user', [
        'business_id' => $business->id,
        'user_id' => $user->id,
        'role' => 'business_owner',
        'is_active' => true,
    ]);

    $this->assertDatabaseHas('clinic_contract_acceptances', [
        'business_id' => $business->id,
        'user_id' => $user->id,
        'platform_contract_id' => $contract->id,
        'contract_version' => '1.0',
        'is_accepted' => true,
        'acceptance_method' => 'checkbox',
    ]);

    expect(
        PersonalAccessToken::where(
            'tokenable_id',
            $user->id
        )->count()
    )->toBe(1);
});

test('business registration requires clinic contract acceptance', function () {
    createPublishedClinicContract();

    $payload = validBusinessRegistrationPayload();
    $payload['contract_accepted'] = false;

    $response = $this->postJson(
        '/api/register/business',
        $payload
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'contract_accepted',
        ]);

    expect(User::where(
        'email',
        'owner@example.com'
    )->exists())->toBeFalse();
});

test('business registration fails when no active clinic contract exists', function () {
    $response = $this->postJson(
        '/api/register/business',
        validBusinessRegistrationPayload()
    );

    $response
        ->assertUnprocessable()
        ->assertJson([
            'success' => false,
            'message' => 'Şu anda kabul edilebilir aktif klinik üyelik sözleşmesi bulunmuyor.',
        ]);

    expect(User::where(
        'email',
        'owner@example.com'
    )->exists())->toBeFalse();
});

test('business registration rejects expired clinic contract', function () {
    PlatformContract::create([
        'contract_type' => 'clinic_membership',
        'title' => 'Süresi Dolmuş Sözleşme',
        'version' => '1.0',
        'content' => 'Test sözleşme içeriği.',
        'status' => 'published',
        'is_required' => true,
        'published_at' => now()->subDays(2),
        'effective_at' => now()->subDays(2),
        'expires_at' => now()->subMinute(),
    ]);

    $response = $this->postJson(
        '/api/register/business',
        validBusinessRegistrationPayload()
    );

    $response
        ->assertUnprocessable()
        ->assertJson([
            'success' => false,
        ]);

    expect(User::where(
        'email',
        'owner@example.com'
    )->exists())->toBeFalse();
});

test('business registration rejects unpublished clinic contract', function () {
    PlatformContract::create([
        'contract_type' => 'clinic_membership',
        'title' => 'Taslak Sözleşme',
        'version' => '1.0',
        'content' => 'Test sözleşme içeriği.',
        'status' => 'draft',
        'is_required' => true,
        'published_at' => null,
        'effective_at' => null,
        'expires_at' => null,
    ]);

    $response = $this->postJson(
        '/api/register/business',
        validBusinessRegistrationPayload()
    );

    $response
        ->assertUnprocessable()
        ->assertJson([
            'success' => false,
        ]);

    expect(User::where(
        'email',
        'owner@example.com'
    )->exists())->toBeFalse();
});

test('business registration validates required fields', function () {
    createPublishedClinicContract();

    $response = $this->postJson(
        '/api/register/business',
        []
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'owner_name',
            'owner_email',
            'password',
            'business_name',
            'city',
            'address',
            'contract_accepted',
        ]);
});

test('business registration rejects duplicate owner email', function () {
    createPublishedClinicContract();

    User::factory()->create([
        'email' => 'owner@example.com',
    ]);

    $response = $this->postJson(
        '/api/register/business',
        validBusinessRegistrationPayload()
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'owner_email',
        ]);
});

test('business registration generates unique business slug', function () {
    createPublishedClinicContract();

    Business::factory()->create([
        'name' => 'Test Diş Kliniği',
        'slug' => 'test-dis-klinigi',
    ]);

    $response = $this->postJson(
        '/api/register/business',
        validBusinessRegistrationPayload()
    );

    $response->assertCreated();

    $this->assertDatabaseHas('businesses', [
        'slug' => 'test-dis-klinigi-2',
    ]);
});

test('business registration generates unique branch slug within the business', function () {
    createPublishedClinicContract();

    $payload = validBusinessRegistrationPayload();

    $response = $this->postJson(
        '/api/register/business',
        $payload
    );

    $response->assertCreated();

    $business = Business::where(
        'name',
        'Test Diş Kliniği'
    )->firstOrFail();

    $this->assertDatabaseHas('branches', [
        'business_id' => $business->id,
        'slug' => 'test-dis-klinigi-merkez',
    ]);

    $response = $this->postJson(
        '/api/register/business',
        array_merge(
            $payload,
            [
                'owner_email' => 'owner2@example.com',
            ]
        )
    );

    $response->assertCreated();

    $secondBusiness = Business::where(
        'name',
        'Test Diş Kliniği'
    )
        ->where('id', '!=', $business->id)
        ->firstOrFail();

    $this->assertDatabaseHas('branches', [
        'business_id' => $secondBusiness->id,
        'slug' => 'test-dis-klinigi-merkez',
    ]);
});

test('business registration stores lowercase owner email', function () {
    createPublishedClinicContract();

    $payload = validBusinessRegistrationPayload();
    $payload['owner_email'] = 'OWNER@EXAMPLE.COM';

    $response = $this->postJson(
        '/api/register/business',
        $payload
    );

    $response->assertCreated();

    $this->assertDatabaseHas('users', [
        'email' => 'owner@example.com',
    ]);
});

test('business registration stores lowercase business email', function () {
    createPublishedClinicContract();

    $payload = validBusinessRegistrationPayload();
    $payload['business_email'] = 'CLINIC@EXAMPLE.COM';

    $response = $this->postJson(
        '/api/register/business',
        $payload
    );

    $response->assertCreated();

    $this->assertDatabaseHas('businesses', [
        'email' => 'clinic@example.com',
    ]);
});

test('business registration defaults branch name to business name', function () {
    createPublishedClinicContract();

    $payload = validBusinessRegistrationPayload();
    unset($payload['branch_name']);

    $response = $this->postJson(
        '/api/register/business',
        $payload
    );

    $response->assertCreated();

    $business = Business::where(
        'name',
        'Test Diş Kliniği'
    )->firstOrFail();

    $this->assertDatabaseHas('branches', [
        'business_id' => $business->id,
        'name' => 'Test Diş Kliniği',
    ]);
});

test('business registration does not create records when validation fails', function () {
    createPublishedClinicContract();

    $payload = validBusinessRegistrationPayload();
    $payload['owner_email'] = 'invalid-email';

    $response = $this->postJson(
        '/api/register/business',
        $payload
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'owner_email',
        ]);

    expect(User::where(
        'email',
        'owner@example.com'
    )->exists())->toBeFalse();

    expect(Business::where(
        'name',
        'Test Diş Kliniği'
    )->exists())->toBeFalse();
});

test('business registration hashes owner password', function () {
    createPublishedClinicContract();

    $response = $this->postJson(
        '/api/register/business',
        validBusinessRegistrationPayload()
    );

    $response->assertCreated();

    $user = User::where(
        'email',
        'owner@example.com'
    )->firstOrFail();

    expect($user->password)
        ->not->toBe('password123')
        ->and(Hash::check(
            'password123',
            $user->password
        ))->toBeTrue();
});