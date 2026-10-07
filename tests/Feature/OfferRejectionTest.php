<?php

use App\Notifications\NotificationType;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Conversation;
use App\Models\Offer;
use App\Models\PatientProfile;
use App\Models\User;
use App\Notifications\OfferRejectedNotification;
use App\Services\BusinessNotificationPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function createOfferRejectionData(): array
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
        'subject' => 'Teklif red testi',
        'status' => 'open',
    ]);

    $offer = Offer::create([
        'conversation_id' => $conversation->id,
        'business_id' => $business->id,
        'branch_id' => null,
        'patient_profile_id' => $patientProfile->id,
        'treatment_id' => null,
        'created_by' => $businessUser->id,
        'title' => 'Reddedilecek Teklif',
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

function rejectionPayload(array $overrides = []): array
{
    return array_merge([
        'rejection_reason' => 'Fiyat benim için uygun değil.',
    ], $overrides);
}

test('patient can reject their own offer', function () {
    Notification::fake();

    $data = createOfferRejectionData();

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson(
            "/api/offers/{$data['offer']->id}/reject",
            rejectionPayload()
        );

    $response->assertOk();

    $response->assertJson([
        'success' => true,
        'message' => 'Teklif reddedildi.',
    ]);

    $this->assertDatabaseHas('offers', [
        'id' => $data['offer']->id,
        'status' => 'rejected',
        'rejection_reason' => 'Fiyat benim için uygun değil.',
    ]);

    expect(
        $data['offer']->fresh()->responded_at
    )->not->toBeNull();

    Notification::assertSentTo(
        $data['businessUser'],
        OfferRejectedNotification::class
    );
});

test('patient cannot reject another patients offer', function () {
    $data = createOfferRejectionData();

    $otherUser = User::factory()->create();

    $response = $this
        ->actingAs($otherUser)
        ->postJson(
            "/api/offers/{$data['offer']->id}/reject",
            rejectionPayload()
        );

    $response->assertForbidden();

    $this->assertDatabaseHas('offers', [
        'id' => $data['offer']->id,
        'status' => 'pending',
    ]);
});

test('business user cannot reject offer', function () {
    $data = createOfferRejectionData();

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/offers/{$data['offer']->id}/reject",
            rejectionPayload()
        );

    $response->assertForbidden();

    $this->assertDatabaseHas('offers', [
        'id' => $data['offer']->id,
        'status' => 'pending',
    ]);
});

test('business user from another business cannot reject offer', function () {
    $data = createOfferRejectionData();

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
            "/api/offers/{$data['offer']->id}/reject",
            rejectionPayload()
        );

    $response->assertForbidden();

    $this->assertDatabaseHas('offers', [
        'id' => $data['offer']->id,
        'status' => 'pending',
    ]);
});

test('unauthenticated user cannot reject offer', function () {
    $data = createOfferRejectionData();

    $response = $this->postJson(
        "/api/offers/{$data['offer']->id}/reject",
        rejectionPayload()
    );

    $response->assertUnauthorized();

    $this->assertDatabaseHas('offers', [
        'id' => $data['offer']->id,
        'status' => 'pending',
    ]);
});

test('patient can reject offer without rejection reason', function () {
    Notification::fake();

    $data = createOfferRejectionData();

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson(
            "/api/offers/{$data['offer']->id}/reject",
            []
        );

    $response->assertOk();

    $response->assertJson([
        'success' => true,
        'message' => 'Teklif reddedildi.',
    ]);

    $this->assertDatabaseHas('offers', [
        'id' => $data['offer']->id,
        'status' => 'rejected',
        'rejection_reason' => null,
    ]);
});

test('rejection reason cannot exceed maximum length', function () {
    $data = createOfferRejectionData();

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson(
            "/api/offers/{$data['offer']->id}/reject",
            rejectionPayload([
                'rejection_reason' => str_repeat('a', 2001),
            ])
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'rejection_reason',
    ]);

    $this->assertDatabaseHas('offers', [
        'id' => $data['offer']->id,
        'status' => 'pending',
    ]);
});

test('patient cannot reject expired offer', function () {
    $data = createOfferRejectionData();

    $data['offer']->update([
        'valid_until' => now()->subDay(),
    ]);

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson(
            "/api/offers/{$data['offer']->id}/reject",
            rejectionPayload()
        );

    $response->assertUnprocessable();

    $response->assertJson([
        'message' => 'Bu teklifin geçerlilik süresi dolmuştur.',
    ]);

    $this->assertDatabaseHas('offers', [
        'id' => $data['offer']->id,
        'status' => 'expired',
    ]);
});

test('patient cannot reject already accepted offer', function () {
    $data = createOfferRejectionData();

    $data['offer']->update([
        'status' => 'accepted',
    ]);

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson(
            "/api/offers/{$data['offer']->id}/reject",
            rejectionPayload()
        );

    $response->assertUnprocessable();

    $response->assertJson([
        'message' => 'Bu teklif artık reddedilemez.',
    ]);

    $this->assertDatabaseHas('offers', [
        'id' => $data['offer']->id,
        'status' => 'accepted',
    ]);
});

test('patient cannot reject already rejected offer', function () {
    $data = createOfferRejectionData();

    $data['offer']->update([
        'status' => 'rejected',
        'rejection_reason' => 'Önceden reddedildi.',
        'responded_at' => now(),
    ]);

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson(
            "/api/offers/{$data['offer']->id}/reject",
            rejectionPayload([
                'rejection_reason' => 'Tekrar reddedilmeye çalışıldı.',
            ])
        );

    $response->assertUnprocessable();

    $response->assertJson([
        'message' => 'Bu teklif artık reddedilemez.',
    ]);

    $this->assertDatabaseHas('offers', [
        'id' => $data['offer']->id,
        'status' => 'rejected',
        'rejection_reason' => 'Önceden reddedildi.',
    ]);
});

test('rejection stores responded at timestamp', function () {
    Notification::fake();

    $data = createOfferRejectionData();

    expect($data['offer']->responded_at)->toBeNull();

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson(
            "/api/offers/{$data['offer']->id}/reject",
            rejectionPayload()
        );

    $response->assertOk();

    expect(
        $data['offer']->fresh()->responded_at
    )->not->toBeNull();
});

test('rejection reason is stored exactly as submitted', function () {
    Notification::fake();

    $data = createOfferRejectionData();

    $reason = 'Tedavi tarihi ve fiyatı benim için uygun değil.';

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson(
            "/api/offers/{$data['offer']->id}/reject",
            [
                'rejection_reason' => $reason,
            ]
        );

    $response->assertOk();

    $this->assertDatabaseHas('offers', [
        'id' => $data['offer']->id,
        'rejection_reason' => $reason,
        'status' => 'rejected',
    ]);
});

test('rejection does not notify business when business notification preference is disabled', function () {
    Notification::fake();

    $data = createOfferRejectionData();

    $preferenceService = app(
        BusinessNotificationPreferenceService::class
    );

    $preferenceService->update(
        $data['business'],
        NotificationType::OFFER_REJECTED,
        false,
        false
    );

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson(
            "/api/offers/{$data['offer']->id}/reject",
            rejectionPayload()
        );

    $response->assertOk();

    $response->assertJson([
        'success' => true,
        'message' => 'Teklif reddedildi.',
    ]);

    Notification::assertNotSentTo(
        $data['businessUser'],
        OfferRejectedNotification::class
    );

    $this->assertDatabaseHas('offers', [
        'id' => $data['offer']->id,
        'status' => 'rejected',
    ]);
});