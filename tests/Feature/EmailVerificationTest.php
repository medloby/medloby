<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

function emailVerificationHash(User $user): string
{
    return sha1($user->getEmailForVerification());
}

function emailVerificationUrl(int $id, string $hash): string
{
    return URL::signedRoute(
        'verification.verify',
        [
            'id' => $id,
            'hash' => $hash,
        ]
    );
}

test('user can verify email with valid verification link', function () {
    Event::fake();

    $user = User::factory()->create([
        'email' => 'patient@example.com',
        'email_verified_at' => null,
    ]);

    $hash = emailVerificationHash($user);

    $response = $this->getJson(
        emailVerificationUrl($user->id, $hash)
    );

    $response
        ->assertSuccessful()
        ->assertJson([
            'success' => true,
            'message' => 'E-posta adresi başarıyla doğrulandı.',
        ]);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

    Event::assertDispatched(
        Verified::class,
        fn (Verified $event) =>
            $event->user->is($user)
    );
});

test('email verification rejects invalid hash', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $response = $this->getJson(
        emailVerificationUrl($user->id, 'invalid-hash')
    );

    $response
        ->assertForbidden()
        ->assertJson([
            'message' => 'Geçersiz doğrulama bağlantısı.',
        ]);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('email verification returns not found for unknown user', function () {
    $response = $this->getJson(
        emailVerificationUrl(
            999999,
            'some-hash'
        )
    );

    $response->assertNotFound();
});

test('already verified user is not verified again', function () {
    Event::fake();

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $hash = emailVerificationHash($user);

    $response = $this->getJson(
        emailVerificationUrl($user->id, $hash)
    );

    $response
        ->assertSuccessful()
        ->assertJson([
            'success' => true,
            'message' => 'E-posta adresi zaten doğrulanmış.',
        ]);

    Event::assertNotDispatched(Verified::class);
});

test('unverified user can request verification email again', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $response = $this
        ->actingAs($user, 'sanctum')
        ->postJson('/api/email/verification-notification');

    $response
        ->assertSuccessful()
        ->assertJson([
            'success' => true,
            'message' => 'Doğrulama e-postası gönderildi.',
        ]);

    Notification::assertSentTo(
        $user,
        \Illuminate\Auth\Notifications\VerifyEmail::class
    );
});

test('verified user does not receive another verification email', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $response = $this
        ->actingAs($user, 'sanctum')
        ->postJson('/api/email/verification-notification');

    $response
        ->assertSuccessful()
        ->assertJson([
            'success' => true,
            'message' => 'E-posta adresi zaten doğrulanmış.',
        ]);

    Notification::assertNothingSent();
});

test('verification email resend requires authentication', function () {
    $this->postJson('/api/email/verification-notification')
        ->assertUnauthorized();
});

test('changing user email invalidates the old verification hash', function () {
    $user = User::factory()->create([
        'email' => 'old@example.com',
        'email_verified_at' => null,
    ]);

    $oldHash = emailVerificationHash($user);

    $user->update([
        'email' => 'new@example.com',
    ]);

    $response = $this->getJson(
        emailVerificationUrl($user->id, $oldHash)
    );

    $response
        ->assertForbidden()
        ->assertJson([
            'message' => 'Geçersiz doğrulama bağlantısı.',
        ]);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});