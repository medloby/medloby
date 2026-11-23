<?php

use App\Models\User;
use App\Notifications\NewOfferNotification;
use App\Notifications\OfferAcceptedNotification;
use App\Notifications\OfferRejectedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createApiNotification(
    User $user,
    string $type,
    array $data = []
): void {
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => $type,
        'data' => array_merge([
            'notification_type' => $type,
            'title' => 'Test bildirimi',
            'message' => 'Bildirim API testi.',
        ], $data),
    ]);
}

test('authenticated user can list their notifications', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    createApiNotification(
        $user,
        NewOfferNotification::class,
        [
            'title' => 'Yeni teklif',
        ]
    );

    createApiNotification(
        $user,
        OfferAcceptedNotification::class,
        [
            'title' => 'Teklif kabul edildi',
        ]
    );

    $response = $this
        ->actingAs($user)
        ->getJson('/api/notifications');

    $response->assertOk();

    $response->assertJson([
        'success' => true,
    ]);

    $response->assertJsonCount(
        2,
        'data.data'
    );
});

test('user can only see their own notifications', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $otherUser = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    createApiNotification(
        $user,
        NewOfferNotification::class
    );

    createApiNotification(
        $otherUser,
        OfferAcceptedNotification::class
    );

    $response = $this
        ->actingAs($user)
        ->getJson('/api/notifications');

    $response->assertOk();

    $response->assertJsonCount(
        1,
        'data.data'
    );

    $response->assertJsonFragment([
        'type' => NewOfferNotification::class,
    ]);

    $response->assertJsonMissing([
        'type' => OfferAcceptedNotification::class,
    ]);
});

test('legacy offer decision notifications omit private offer details in every response', function () {
    $user = User::factory()->create();
    createApiNotification($user, OfferRejectedNotification::class, [
        'offer_id' => 42,
        'conversation_id' => 17,
        'patient_profile_id' => 12,
        'treatment_id' => 3,
        'rejection_reason' => 'Ret gerekçesi yalnızca teklifte görünmeli.',
    ]);
    $notification = $user->notifications()->firstOrFail();

    $this->actingAs($user);

    foreach (['/api/notifications', '/api/notifications/unread'] as $url) {
        $this->getJson($url)->assertOk()
            ->assertJsonPath('data.data.0.data.offer_id', 42)
            ->assertJsonPath('data.data.0.data.conversation_id', 17)
            ->assertJsonMissingPath('data.data.0.data.patient_profile_id')
            ->assertJsonMissingPath('data.data.0.data.treatment_id')
            ->assertJsonMissingPath('data.data.0.data.rejection_reason');
    }

    $this->postJson("/api/notifications/{$notification->id}/read")->assertOk()
        ->assertJsonPath('data.data.offer_id', 42)
        ->assertJsonMissingPath('data.data.patient_profile_id')
        ->assertJsonMissingPath('data.data.treatment_id')
        ->assertJsonMissingPath('data.data.rejection_reason');
});

test('authenticated user can list only unread notifications', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    createApiNotification(
        $user,
        NewOfferNotification::class
    );

    $readNotification = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => OfferAcceptedNotification::class,
        'data' => [
            'notification_type' => 'offer_accepted',
            'title' => 'Okunmuş bildirim',
            'message' => 'Bu bildirim okunmuş.',
        ],
        'read_at' => now(),
    ]);

    $response = $this
        ->actingAs($user)
        ->getJson('/api/notifications/unread');

    $response->assertOk();

    $response->assertJson([
        'success' => true,
    ]);

    $response->assertJsonCount(
        1,
        'data.data'
    );

    $response->assertJsonFragment([
        'type' => NewOfferNotification::class,
    ]);

    $response->assertJsonMissing([
        'type' => $readNotification->type,
    ]);
});

test('authenticated user can get unread notification count', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    createApiNotification(
        $user,
        NewOfferNotification::class
    );

    createApiNotification(
        $user,
        OfferRejectedNotification::class
    );

    $readNotification = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => OfferAcceptedNotification::class,
        'data' => [
            'notification_type' => 'offer_accepted',
            'title' => 'Okunmuş bildirim',
            'message' => 'Bu bildirim okunmuş.',
        ],
        'read_at' => now(),
    ]);

    expect($readNotification)->not->toBeNull();

    $response = $this
        ->actingAs($user)
        ->getJson('/api/notifications/unread-count');

    $response->assertOk();

    $response->assertJson([
        'success' => true,
        'data' => [
            'count' => 2,
        ],
    ]);
});

test('authenticated user can mark one notification as read', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    createApiNotification(
        $user,
        NewOfferNotification::class
    );

    $notification = $user->unreadNotifications()->first();

    expect($notification)->not->toBeNull();

    $response = $this
        ->actingAs($user)
        ->postJson(
            "/api/notifications/{$notification->id}/read"
        );

    $response->assertOk();

    $response->assertJson([
        'success' => true,
        'message' => 'Bildirim okundu olarak işaretlendi.',
    ]);

    $this->assertDatabaseHas('notifications', [
        'id' => $notification->id,
    ]);

    expect(
        $notification->fresh()->read_at
    )->not->toBeNull();
});

test('user cannot mark another users notification as read', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $otherUser = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    createApiNotification(
        $otherUser,
        NewOfferNotification::class
    );

    $notification = $otherUser->notifications()->first();

    expect($notification)->not->toBeNull();

    $response = $this
        ->actingAs($user)
        ->postJson(
            "/api/notifications/{$notification->id}/read"
        );

    $response->assertNotFound();

    expect(
        $notification->fresh()->read_at
    )->toBeNull();
});

test('authenticated user can mark all notifications as read', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    createApiNotification(
        $user,
        NewOfferNotification::class
    );

    createApiNotification(
        $user,
        OfferRejectedNotification::class
    );

    $response = $this
        ->actingAs($user)
        ->postJson('/api/notifications/read-all');

    $response->assertOk();

    $response->assertJson([
        'success' => true,
        'message' => 'Tüm bildirimler okundu olarak işaretlendi.',
        'data' => [
            'updated_count' => 2,
        ],
    ]);

    expect(
        $user->fresh()->unreadNotifications()->count()
    )->toBe(0);
});

test('authenticated user can delete their notification', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    createApiNotification(
        $user,
        NewOfferNotification::class
    );

    $notification = $user->notifications()->first();

    expect($notification)->not->toBeNull();

    $response = $this
        ->actingAs($user)
        ->deleteJson(
            "/api/notifications/{$notification->id}"
        );

    $response->assertOk();

    $response->assertJson([
        'success' => true,
        'message' => 'Bildirim silindi.',
    ]);

    $this->assertDatabaseMissing('notifications', [
        'id' => $notification->id,
    ]);
});

test('user cannot delete another users notification', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $otherUser = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    createApiNotification(
        $otherUser,
        NewOfferNotification::class
    );

    $notification = $otherUser->notifications()->first();

    expect($notification)->not->toBeNull();

    $response = $this
        ->actingAs($user)
        ->deleteJson(
            "/api/notifications/{$notification->id}"
        );

    $response->assertNotFound();

    $this->assertDatabaseHas('notifications', [
        'id' => $notification->id,
    ]);
});

test('unauthenticated user cannot access notifications api', function () {
    $this
        ->getJson('/api/notifications')
        ->assertUnauthorized();

    $this
        ->getJson('/api/notifications/unread')
        ->assertUnauthorized();

    $this
        ->getJson('/api/notifications/unread-count')
        ->assertUnauthorized();
});
