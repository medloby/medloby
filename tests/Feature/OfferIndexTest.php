<?php

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Conversation;
use App\Models\Offer;
use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createOfferIndexData(): array
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
        'subject' => 'Teklif listeleme testi',
        'status' => 'open',
    ]);

    $offer = Offer::create([
        'conversation_id' => $conversation->id,
        'business_id' => $business->id,
        'branch_id' => null,
        'patient_profile_id' => $patientProfile->id,
        'treatment_id' => null,
        'created_by' => $businessUser->id,
        'title' => 'Test Teklifi',
        'description' => 'Test teklif açıklaması.',
        'amount' => 25000,
        'currency' => 'TRY',
        'valid_until' => now()->addDays(3),
        'status' => 'pending',
    ]);

    return [
        'business' => $business,
        'patientUser' => $patientUser,
        'patientProfile' => $patientProfile,
        'businessUser' => $businessUser,
        'conversation' => $conversation,
        'offer' => $offer,
    ];
}

function createSecondOfferForConversation(
    Conversation $conversation,
    BusinessUser|User $businessUser,
    PatientProfile $patientProfile
): Offer {
    return Offer::create([
        'conversation_id' => $conversation->id,
        'business_id' => $conversation->business_id,
        'branch_id' => $conversation->branch_id,
        'patient_profile_id' => $patientProfile->id,
        'treatment_id' => null,
        'created_by' => $businessUser->user_id ?? $businessUser->id,
        'title' => 'İkinci Test Teklifi',
        'description' => 'İkinci teklif.',
        'amount' => 30000,
        'currency' => 'TRY',
        'valid_until' => now()->addDays(5),
        'status' => 'pending',
    ]);
}

test('patient can list offers from their own conversation', function () {
    $data = createOfferIndexData();

    $response = $this
        ->actingAs($data['patientUser'])
        ->getJson(
            "/api/conversations/{$data['conversation']->id}/offers"
        );

    $response->assertOk();

    $response->assertJson([
        'success' => true,
    ]);

    $response->assertJsonCount(
        1,
        'data'
    );

    $response->assertJsonFragment([
        'id' => $data['offer']->id,
        'title' => 'Test Teklifi',
    ]);
});

test('business user can list offers from their business conversation', function () {
    $data = createOfferIndexData();

    $response = $this
        ->actingAs($data['businessUser'])
        ->getJson(
            "/api/conversations/{$data['conversation']->id}/offers"
        );

    $response->assertOk();

    $response->assertJson([
        'success' => true,
    ]);

    $response->assertJsonCount(
        1,
        'data'
    );

    $response->assertJsonFragment([
        'id' => $data['offer']->id,
        'title' => 'Test Teklifi',
    ]);
});

test('patient cannot list offers from another patients conversation', function () {
    $data = createOfferIndexData();

    $otherUser = User::factory()->create();

    $otherPatientProfile = PatientProfile::create([
        'user_id' => $otherUser->id,
        'first_name' => 'Diğer',
        'last_name' => 'Hasta',
        'phone' => '05551111111',
    ]);

    $otherConversation = Conversation::create([
        'business_id' => $data['business']->id,
        'branch_id' => null,
        'patient_profile_id' => $otherPatientProfile->id,
        'subject' => 'Başka hasta görüşmesi',
        'status' => 'open',
    ]);

    Offer::create([
        'conversation_id' => $otherConversation->id,
        'business_id' => $data['business']->id,
        'branch_id' => null,
        'patient_profile_id' => $otherPatientProfile->id,
        'treatment_id' => null,
        'created_by' => $data['businessUser']->id,
        'title' => 'Başka Hasta Teklifi',
        'description' => 'Başka hastaya ait teklif.',
        'amount' => 15000,
        'currency' => 'TRY',
        'valid_until' => now()->addDays(3),
        'status' => 'pending',
    ]);

    $response = $this
        ->actingAs($data['patientUser'])
        ->getJson(
            "/api/conversations/{$otherConversation->id}/offers"
        );

    $response->assertForbidden();
});

test('business user from another business cannot list offers', function () {
    $data = createOfferIndexData();

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
        ->getJson(
            "/api/conversations/{$data['conversation']->id}/offers"
        );

    $response->assertForbidden();
});

test('inactive business membership cannot list business offers', function () {
    $data = createOfferIndexData();

    $data['businessUser']
        ->businessMemberships()
        ->update([
            'is_active' => false,
        ]);

    $response = $this
        ->actingAs($data['businessUser'])
        ->getJson(
            "/api/conversations/{$data['conversation']->id}/offers"
        );

    $response->assertForbidden();
});

test('unrelated authenticated user cannot list offers', function () {
    $data = createOfferIndexData();

    $unrelatedUser = User::factory()->create();

    $response = $this
        ->actingAs($unrelatedUser)
        ->getJson(
            "/api/conversations/{$data['conversation']->id}/offers"
        );

    $response->assertForbidden();
});

test('unauthenticated user cannot list offers', function () {
    $data = createOfferIndexData();

    $response = $this->getJson(
        "/api/conversations/{$data['conversation']->id}/offers"
    );

    $response->assertUnauthorized();
});

test('business user can list multiple offers from their conversation', function () {
    $data = createOfferIndexData();

    createSecondOfferForConversation(
        $data['conversation'],
        $data['businessUser'],
        $data['patientProfile']
    );

    $response = $this
        ->actingAs($data['businessUser'])
        ->getJson(
            "/api/conversations/{$data['conversation']->id}/offers"
        );

    $response->assertOk();

    $response->assertJsonCount(
        2,
        'data'
    );

    $response->assertJsonFragment([
        'title' => 'Test Teklifi',
    ]);

    $response->assertJsonFragment([
        'title' => 'İkinci Test Teklifi',
    ]);
});