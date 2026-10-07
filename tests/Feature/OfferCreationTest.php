<?php

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Conversation;
use App\Models\PatientProfile;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\User;
use App\Notifications\NewOfferNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function createOfferCreationData(): array
{
    $business = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
    ]);

    $patientUser = User::factory()->create();

    $patientProfile = PatientProfile::create([
        'user_id' => $patientUser->id,
        'first_name' => 'Test',
        'last_name' => 'Hasta',
        'phone' => '05550000000',
    ]);

    $businessUser = User::factory()->create();

    BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $businessUser->id,
        'role' => 'owner',
        'is_active' => true,
    ]);

    $conversation = Conversation::create([
        'business_id' => $business->id,
        'branch_id' => null,
        'patient_profile_id' => $patientProfile->id,
        'subject' => 'Teklif oluşturma testi',
        'status' => 'open',
    ]);

    $category = TreatmentCategory::create([
        'name' => 'Teklif Oluşturma Kategorisi',
        'slug' => 'teklif-olusturma-kategorisi-'.uniqid(),
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $treatment = Treatment::create([
        'treatment_category_id' => $category->id,
        'name' => 'Test Tedavisi',
        'slug' => 'test-tedavisi-'.uniqid(),
        'duration_minutes' => 60,
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    return [
        'business' => $business,
        'patientUser' => $patientUser,
        'patientProfile' => $patientProfile,
        'businessUser' => $businessUser,
        'conversation' => $conversation,
        'category' => $category,
        'treatment' => $treatment,
    ];
}

function offerCreationPayload(array $data, array $overrides = []): array
{
    return array_merge([
        'treatment_id' => $data['treatment']->id,
        'title' => 'Saç Ekimi Teklifi',
        'description' => 'Test teklif açıklaması.',
        'amount' => 25000,
        'currency' => 'try',
        'valid_until' => now()->addDays(3)->toDateTimeString(),
    ], $overrides);
}

test('business user can create offer for conversation', function () {
    Notification::fake();

    $data = createOfferCreationData();

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            offerCreationPayload($data)
        );

    $response->assertCreated();

    $response->assertJson([
        'success' => true,
        'message' => 'Teklif başarıyla oluşturuldu.',
    ]);

    $this->assertDatabaseHas('offers', [
        'conversation_id' => $data['conversation']->id,
        'business_id' => $data['business']->id,
        'patient_profile_id' => $data['patientProfile']->id,
        'treatment_id' => $data['treatment']->id,
        'created_by' => $data['businessUser']->id,
        'title' => 'Saç Ekimi Teklifi',
        'amount' => 25000,
        'currency' => 'TRY',
        'status' => 'pending',
    ]);

    Notification::assertSentTo(
        $data['patientUser'],
        NewOfferNotification::class
    );
});

test('patient cannot create offer', function () {
    $data = createOfferCreationData();

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            offerCreationPayload($data)
        );

    $response->assertForbidden();

    $this->assertDatabaseCount('offers', 0);
});

test('business user from another business cannot create offer', function () {
    $data = createOfferCreationData();

    $otherBusiness = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
    ]);

    $otherBusinessUser = User::factory()->create();

    BusinessUser::create([
        'business_id' => $otherBusiness->id,
        'user_id' => $otherBusinessUser->id,
        'role' => 'owner',
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($otherBusinessUser)
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            offerCreationPayload($data)
        );

    $response->assertForbidden();

    $this->assertDatabaseCount('offers', 0);
});

test('inactive business membership cannot create offer', function () {
    $data = createOfferCreationData();

    $data['businessUser']
        ->businessMemberships()
        ->update([
            'is_active' => false,
        ]);

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            offerCreationPayload($data)
        );

    $response->assertForbidden();

    $this->assertDatabaseCount('offers', 0);
});

test('unauthenticated user cannot create offer', function () {
    $data = createOfferCreationData();

    $response = $this
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            offerCreationPayload($data)
        );

    $response->assertUnauthorized();

    $this->assertDatabaseCount('offers', 0);
});

test('offer cannot be created for closed conversation', function () {
    $data = createOfferCreationData();

    $data['conversation']->update([
        'status' => 'closed',
    ]);

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            offerCreationPayload($data)
        );

    $response
        ->assertUnprocessable()
        ->assertJson([
            'message' => 'Kapalı bir görüşmeye teklif oluşturulamaz.',
        ]);

    $this->assertDatabaseCount('offers', 0);
});

test('offer creation requires treatment', function () {
    $data = createOfferCreationData();

    $payload = offerCreationPayload($data);
    unset($payload['treatment_id']);

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            $payload
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'treatment_id',
    ]);

    $this->assertDatabaseCount('offers', 0);
});

test('offer creation requires title', function () {
    $data = createOfferCreationData();

    $payload = offerCreationPayload($data);
    unset($payload['title']);

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            $payload
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'title',
    ]);

    $this->assertDatabaseCount('offers', 0);
});

test('offer creation requires amount', function () {
    $data = createOfferCreationData();

    $payload = offerCreationPayload($data);
    unset($payload['amount']);

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            $payload
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'amount',
    ]);

    $this->assertDatabaseCount('offers', 0);
});

test('offer creation requires currency', function () {
    $data = createOfferCreationData();

    $payload = offerCreationPayload($data);
    unset($payload['currency']);

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            $payload
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'currency',
    ]);

    $this->assertDatabaseCount('offers', 0);
});

test('offer creation rejects negative amount', function () {
    $data = createOfferCreationData();

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            offerCreationPayload($data, [
                'amount' => -1,
            ])
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'amount',
    ]);

    $this->assertDatabaseCount('offers', 0);
});

test('offer creation rejects invalid currency length', function () {
    $data = createOfferCreationData();

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            offerCreationPayload($data, [
                'currency' => 'TRYX',
            ])
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'currency',
    ]);

    $this->assertDatabaseCount('offers', 0);
});

test('offer creation rejects valid until in the past', function () {
    $data = createOfferCreationData();

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            offerCreationPayload($data, [
                'valid_until' => now()->subMinute()->toDateTimeString(),
            ])
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'valid_until',
    ]);

    $this->assertDatabaseCount('offers', 0);
});

test('offer creation rejects inactive treatment', function () {
    $data = createOfferCreationData();

    $data['treatment']->update([
        'is_active' => false,
    ]);

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            offerCreationPayload($data)
        );

    $response
        ->assertUnprocessable()
        ->assertJson([
            'message' => 'Seçilen tedavi aktif değil.',
        ]);

    $this->assertDatabaseCount('offers', 0);
});

test('offer creation rejects nonexistent treatment', function () {
    $data = createOfferCreationData();

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            offerCreationPayload($data, [
                'treatment_id' => 999999,
            ])
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'treatment_id',
    ]);

    $this->assertDatabaseCount('offers', 0);
});

test('offer creation accepts optional description', function () {
    Notification::fake();

    $data = createOfferCreationData();

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            offerCreationPayload($data, [
                'description' => null,
            ])
        );

    $response->assertCreated();

    $this->assertDatabaseHas('offers', [
        'conversation_id' => $data['conversation']->id,
        'description' => null,
    ]);
});

test('offer creation can omit valid until', function () {
    Notification::fake();

    $data = createOfferCreationData();

    $payload = offerCreationPayload($data);
    unset($payload['valid_until']);

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            $payload
        );

    $response->assertCreated();

    $this->assertDatabaseHas('offers', [
        'conversation_id' => $data['conversation']->id,
        'valid_until' => null,
    ]);
});

test('offer creation stores currency in uppercase', function () {
    Notification::fake();

    $data = createOfferCreationData();

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            offerCreationPayload($data, [
                'currency' => 'usd',
            ])
        );

    $response->assertCreated();

    $this->assertDatabaseHas('offers', [
        'conversation_id' => $data['conversation']->id,
        'currency' => 'USD',
    ]);
});

test('offer creation uses conversation business branch and patient automatically', function () {
    Notification::fake();

    $data = createOfferCreationData();

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            offerCreationPayload($data)
        );

    $response->assertCreated();

    $this->assertDatabaseHas('offers', [
        'conversation_id' => $data['conversation']->id,
        'business_id' => $data['conversation']->business_id,
        'branch_id' => null,
        'patient_profile_id' => $data['conversation']->patient_profile_id,
        'created_by' => $data['businessUser']->id,
    ]);
});

test('offer creation rejects treatment inactive for conversation branch', function () {
    $data = createOfferCreationData();

    $branchId = DB::table('branches')->insertGetId([
        'business_id' => $data['business']->id,
        'name' => 'Teklif Şubesi',
        'slug' => 'teklif-subesi-'.uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $data['conversation']->update([
        'branch_id' => $branchId,
    ]);

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            offerCreationPayload($data)
        );

    $response
        ->assertUnprocessable()
        ->assertJson([
            'message' => 'Seçilen tedavi bu şubede aktif değil.',
        ]);

    $this->assertDatabaseCount('offers', 0);
});

test('offer creation succeeds when treatment is active for conversation branch', function () {
    Notification::fake();

    $data = createOfferCreationData();

    $branchId = DB::table('branches')->insertGetId([
        'business_id' => $data['business']->id,
        'name' => 'Aktif Tedavi Şubesi',
        'slug' => 'aktif-tedavi-subesi-'.uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('branch_treatment')->insert([
        'branch_id' => $branchId,
        'treatment_id' => $data['treatment']->id,
        'duration_minutes' => 60,
        'is_online_bookable' => true,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $data['conversation']->update([
        'branch_id' => $branchId,
    ]);

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$data['conversation']->id}/offers",
            offerCreationPayload($data)
        );

    $response->assertCreated();

    $this->assertDatabaseHas('offers', [
        'conversation_id' => $data['conversation']->id,
        'branch_id' => $branchId,
        'treatment_id' => $data['treatment']->id,
        'status' => 'pending',
    ]);
});