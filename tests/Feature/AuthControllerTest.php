<?php

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('user can login with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response
        ->assertSuccessful()
        ->assertJson([
            'success' => true,
            'message' => 'Giriş başarılı.',
            'data' => [
                'token_type' => 'Bearer',
            ],
        ])
        ->assertJsonPath(
            'data.user.id',
            $user->id
        );

    expect(PersonalAccessToken::where(
        'tokenable_id',
        $user->id
    )->count())->toBe(1);
});

test('login rejects invalid password', function () {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('correct-password'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'test@example.com',
        'password' => 'wrong-password',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'email',
        ]);
});

test('login rejects unknown email', function () {
    $response = $this->postJson('/api/login', [
        'email' => 'unknown@example.com',
        'password' => 'password123',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'email',
        ]);
});

test('login validates required fields', function () {
    $response = $this->postJson('/api/login', []);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'email',
            'password',
        ]);
});

test('login validates email format', function () {
    $response = $this->postJson('/api/login', [
        'email' => 'not-an-email',
        'password' => 'password123',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'email',
        ]);
});

test('login removes previous access tokens before creating a new token', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);

    $user->createToken('old-token');
    $user->createToken('another-old-token');

    expect(PersonalAccessToken::where(
        'tokenable_id',
        $user->id
    )->count())->toBe(2);

    $response = $this->postJson('/api/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response->assertSuccessful();

    expect(PersonalAccessToken::where(
        'tokenable_id',
        $user->id
    )->count())->toBe(1);
});

test('authenticated user can view their profile with me endpoint', function () {
    $user = User::factory()->create();

    $response = $this->actingAs(
        $user,
        'sanctum'
    )->getJson('/api/me');

    $response
        ->assertSuccessful()
        ->assertJson([
            'success' => true,
        ])
        ->assertJsonPath(
            'data.user.id',
            $user->id
        );
});

test('me endpoint returns authenticated user business memberships', function () {
    $user = User::factory()->create();

    $business = Business::factory()->create();

    BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'is_active' => true,
    ]);

    $response = $this->actingAs(
        $user,
        'sanctum'
    )->getJson('/api/me');

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'data.user.id',
            $user->id
        )
        ->assertJsonPath(
            'data.user.business_memberships.0.business.id',
            $business->id
        );
});

test('me endpoint requires authentication', function () {
    $this->getJson('/api/me')
        ->assertUnauthorized();
});

test('authenticated user can logout', function () {
    $user = User::factory()->create();

    $token = $user->createToken(
        'test-token'
    );

    $response = $this
        ->withToken($token->plainTextToken)
        ->postJson('/api/logout');

    $response
        ->assertSuccessful()
        ->assertJson([
            'success' => true,
            'message' => 'Çıkış başarılı.',
        ]);

    expect(PersonalAccessToken::find(
        $token->accessToken->id
    ))->toBeNull();
});

test('logout requires authentication', function () {
    $this->postJson('/api/logout')
        ->assertUnauthorized();
});