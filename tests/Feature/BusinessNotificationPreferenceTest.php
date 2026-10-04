<?php

use App\Models\Business;
use App\Models\BusinessNotificationPreference;
use App\Models\BusinessUser;
use App\Models\User;
use App\Services\BusinessNotificationPreferenceService;
use App\Notifications\NotificationType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('business notification preferences use default values when no record exists', function () {
    $business = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
    ]);

    $service = app(BusinessNotificationPreferenceService::class);

    $preferences = $service->getAll($business);

    expect($preferences)->toHaveCount(
        count(NotificationType::all())
    );

    $newAppointment = $preferences->firstWhere(
        'notification_type',
        NotificationType::NEW_APPOINTMENT
    );

    expect($newAppointment['in_app_enabled'])->toBeTrue();
    expect($newAppointment['email_enabled'])->toBeTrue();

    $newOffer = $preferences->firstWhere(
        'notification_type',
        NotificationType::NEW_OFFER
    );

    expect($newOffer['in_app_enabled'])->toBeTrue();
    expect($newOffer['email_enabled'])->toBeFalse();
});

test('business can update notification preference', function () {
    $business = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
    ]);

    $service = app(BusinessNotificationPreferenceService::class);

    $preference = $service->update(
        $business,
        NotificationType::OFFER_ACCEPTED,
        true,
        true
    );

    expect($preference)->toBeInstanceOf(
        BusinessNotificationPreference::class
    );

    expect($preference->business_id)
        ->toBe($business->id);

    expect($preference->notification_type)
        ->toBe(NotificationType::OFFER_ACCEPTED);

    expect($preference->in_app_enabled)
        ->toBeTrue();

    expect($preference->email_enabled)
        ->toBeTrue();

    $this->assertDatabaseHas(
        'business_notification_preferences',
        [
            'business_id' => $business->id,
            'notification_type' =>
                NotificationType::OFFER_ACCEPTED,
            'in_app_enabled' => true,
            'email_enabled' => true,
        ]
    );
});

test('business notification preference can be disabled', function () {
    $business = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
    ]);

    $service = app(BusinessNotificationPreferenceService::class);

    $service->update(
        $business,
        NotificationType::NEW_OFFER,
        false,
        false
    );

    expect(
        $service->isInAppEnabled(
            $business,
            NotificationType::NEW_OFFER
        )
    )->toBeFalse();

    expect(
        $service->isEmailEnabled(
            $business,
            NotificationType::NEW_OFFER
        )
    )->toBeFalse();
});

test('business notification preferences can be reset to defaults', function () {
    $business = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
    ]);

    $service = app(BusinessNotificationPreferenceService::class);

    $service->update(
        $business,
        NotificationType::NEW_APPOINTMENT,
        false,
        false
    );

    $service->update(
        $business,
        NotificationType::OFFER_ACCEPTED,
        false,
        true
    );

    $service->resetToDefaults($business);

    expect(
        $service->isInAppEnabled(
            $business,
            NotificationType::NEW_APPOINTMENT
        )
    )->toBeTrue();

    expect(
        $service->isEmailEnabled(
            $business,
            NotificationType::NEW_APPOINTMENT
        )
    )->toBeTrue();

    expect(
        $service->isInAppEnabled(
            $business,
            NotificationType::OFFER_ACCEPTED
        )
    )->toBeTrue();

    expect(
        $service->isEmailEnabled(
            $business,
            NotificationType::OFFER_ACCEPTED
        )
    )->toBeFalse();
});

test('business owner can view notification preferences through api', function () {
    $business = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
    ]);

    $owner = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $owner->id,
        'role' => 'business_owner',
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($owner)
        ->getJson(
            "/api/businesses/{$business->id}/notification-preferences"
        );

    $response->assertOk();

    $response->assertJson([
        'success' => true,
    ]);

    $response->assertJsonCount(
        count(NotificationType::all()),
        'data'
    );
});

test('business owner can update notification preference through api', function () {
    $business = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
    ]);

    $owner = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $owner->id,
        'role' => 'business_owner',
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($owner)
        ->putJson(
            "/api/businesses/{$business->id}/notification-preferences/"
            . NotificationType::OFFER_ACCEPTED,
            [
                'in_app_enabled' => true,
                'email_enabled' => true,
            ]
        );

    $response->assertOk();

    $response->assertJson([
        'success' => true,
        'data' => [
            'notification_type' =>
                NotificationType::OFFER_ACCEPTED,
            'in_app_enabled' => true,
            'email_enabled' => true,
        ],
    ]);

    $this->assertDatabaseHas(
        'business_notification_preferences',
        [
            'business_id' => $business->id,
            'notification_type' =>
                NotificationType::OFFER_ACCEPTED,
            'in_app_enabled' => true,
            'email_enabled' => true,
        ]
    );
});

test('inactive business user cannot update notification preferences', function () {
    $business = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
    ]);

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'role' => 'business_owner',
        'is_active' => false,
    ]);

    $response = $this
        ->actingAs($user)
        ->putJson(
            "/api/businesses/{$business->id}/notification-preferences/"
            . NotificationType::NEW_OFFER,
            [
                'in_app_enabled' => false,
                'email_enabled' => false,
            ]
        );

    $response->assertForbidden();
});

test('regular business staff cannot update notification preferences', function () {
    $business = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
    ]);

    $staff = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $staff->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($staff)
        ->putJson(
            "/api/businesses/{$business->id}/notification-preferences/"
            . NotificationType::NEW_OFFER,
            [
                'in_app_enabled' => false,
                'email_enabled' => false,
            ]
        );

    $response->assertForbidden();

    $this->assertDatabaseMissing(
        'business_notification_preferences',
        [
            'business_id' => $business->id,
            'notification_type' =>
                NotificationType::NEW_OFFER,
        ]
    );
});

test('business owner can reset notification preferences through api', function () {
    $business = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
    ]);

    $owner = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $owner->id,
        'role' => 'business_owner',
        'is_active' => true,
    ]);

    $service = app(BusinessNotificationPreferenceService::class);

    $service->update(
        $business,
        NotificationType::NEW_OFFER,
        false,
        true
    );

    $response = $this
        ->actingAs($owner)
        ->postJson(
            "/api/businesses/{$business->id}/notification-preferences/reset"
        );

    $response->assertOk();

    $response->assertJson([
        'success' => true,
    ]);

    expect(
        $service->isInAppEnabled(
            $business,
            NotificationType::NEW_OFFER
        )
    )->toBeTrue();

    expect(
        $service->isEmailEnabled(
            $business,
            NotificationType::NEW_OFFER
        )
    )->toBeFalse();
});