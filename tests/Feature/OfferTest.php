<?php

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Conversation;
use App\Models\Offer;
use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createOfferTestData(): array
{
    $business = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
    ]);

    $patientUser = User::factory()->create();

    $businessUser = User::factory()->create();

    BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $businessUser->id,
        'role' => 'owner',
        'is_active' => true,
    ]);

    $patientProfile = PatientProfile::create([
        'user_id' => $patientUser->id,
        'first_name' => 'Test',
        'last_name' => 'Hasta',
        'phone' => '05550000000',
    ]);

    $conversation = Conversation::create([
        'business_id' => $business->id,
        'branch_id' => null,
        'patient_profile_id' => $patientProfile->id,
        'subject' => 'Teklif testi',
        'status' => 'open',
    ]);

    return [
        'business' => $business,
        'patientUser' => $patientUser,
        'businessUser' => $businessUser,
        'patientProfile' => $patientProfile,
        'conversation' => $conversation,
    ];
}

test('patient can view offers belonging to their conversation', function () {
    $data = createOfferTestData();

    $offer = Offer::create([
        'conversation_id' => $data['conversation']->id,
        'business_id' => $data['business']->id,
        'branch_id' => null,
        'patient_profile_id' => $data['patientProfile']->id,
        'treatment_id' => null,
        'created_by' => $data['businessUser']->id,
        'title' => 'Test Teklif',
        'description' => 'Test teklif açıklaması',
        'amount' => 1000,
        'currency' => 'TRY',
        'valid_until' => now()->addDays(3),
        'status' => 'pending',
    ]);

    $response = $this
        ->actingAs($data['patientUser'])
        ->getJson(
            "/api/conversations/{$data['conversation']->id}/offers"
        );

    $response->assertOk();

    $response->assertJsonFragment([
        'id' => $offer->id,
    ]);
});

test('patient can accept their own offer', function () {
    $data = createOfferTestData();

    $offer = Offer::create([
        'conversation_id' => $data['conversation']->id,
        'business_id' => $data['business']->id,
        'branch_id' => null,
        'patient_profile_id' => $data['patientProfile']->id,
        'treatment_id' => null,
        'created_by' => $data['businessUser']->id,
        'title' => 'Kabul Edilecek Teklif',
        'description' => null,
        'amount' => 1500,
        'currency' => 'TRY',
        'valid_until' => now()->addDays(3),
        'status' => 'pending',
    ]);

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson(
            "/api/offers/{$offer->id}/accept"
        );

    $response->assertOk();

    $response->assertJson([
        'success' => true,
        'message' => 'Teklif kabul edildi.',
    ]);

    $this->assertDatabaseHas('offers', [
        'id' => $offer->id,
        'status' => 'accepted',
    ]);
});

test('patient cannot accept another patients offer', function () {
    $data = createOfferTestData();

    $otherUser = User::factory()->create();

    $offer = Offer::create([
        'conversation_id' => $data['conversation']->id,
        'business_id' => $data['business']->id,
        'branch_id' => null,
        'patient_profile_id' => $data['patientProfile']->id,
        'treatment_id' => null,
        'created_by' => $data['businessUser']->id,
        'title' => 'Başkasının Teklifi',
        'description' => null,
        'amount' => 1500,
        'currency' => 'TRY',
        'valid_until' => now()->addDays(3),
        'status' => 'pending',
    ]);

    $response = $this
        ->actingAs($otherUser)
        ->postJson(
            "/api/offers/{$offer->id}/accept"
        );

    $response->assertForbidden();

    $this->assertDatabaseHas('offers', [
        'id' => $offer->id,
        'status' => 'pending',
    ]);
});

test('patient cannot accept expired offer', function () {
    $data = createOfferTestData();

    $offer = Offer::create([
        'conversation_id' => $data['conversation']->id,
        'business_id' => $data['business']->id,
        'branch_id' => null,
        'patient_profile_id' => $data['patientProfile']->id,
        'treatment_id' => null,
        'created_by' => $data['businessUser']->id,
        'title' => 'Süresi Dolmuş Teklif',
        'description' => null,
        'amount' => 1500,
        'currency' => 'TRY',
        'valid_until' => now()->subDay(),
        'status' => 'pending',
    ]);

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson(
            "/api/offers/{$offer->id}/accept"
        );

    $response->assertUnprocessable();

    $response->assertJson([
        'message' => 'Bu teklifin geçerlilik süresi dolmuştur.',
    ]);

    $this->assertDatabaseHas('offers', [
        'id' => $offer->id,
        'status' => 'expired',
    ]);
});

test('patient cannot accept already accepted offer', function () {
    $data = createOfferTestData();

    $offer = Offer::create([
        'conversation_id' => $data['conversation']->id,
        'business_id' => $data['business']->id,
        'branch_id' => null,
        'patient_profile_id' => $data['patientProfile']->id,
        'treatment_id' => null,
        'created_by' => $data['businessUser']->id,
        'title' => 'Kabul Edilmiş Teklif',
        'description' => null,
        'amount' => 1500,
        'currency' => 'TRY',
        'valid_until' => now()->addDays(3),
        'status' => 'accepted',
    ]);

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson(
            "/api/offers/{$offer->id}/accept"
        );

    $response->assertUnprocessable();

    $response->assertJson([
        'message' => 'Bu teklif artık kabul edilemez.',
    ]);
});