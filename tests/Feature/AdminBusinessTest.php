<?php

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('non admin user cannot access admin business list', function () {
    $user = User::factory()->create([
        'is_admin' => false,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/admin/businesses');

    $response
        ->assertStatus(403)
        ->assertJson([
            'success' => false,
            'message' => 'Bu işlem için admin yetkisi gereklidir.',
        ]);
});

test('admin can access pending business applications', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'email_verified_at' => now(),
    ]);

    $pendingBusiness = Business::factory()->create([
        'status' => 'pending',
        'is_verified' => false,
        'verified_at' => null,
    ]);

    Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
        'verified_at' => now(),
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/businesses');

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $response->assertJsonPath(
        'data.data.0.id',
        $pendingBusiness->id
    );

    expect($response->json('data.data'))
        ->toHaveCount(1);
});

test('admin can view a business application detail', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'email_verified_at' => now(),
    ]);

    $business = Business::factory()->create([
        'status' => 'pending',
        'is_verified' => false,
        'verified_at' => null,
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson(
            "/api/admin/businesses/{$business->id}"
        );

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $response->assertJsonPath(
        'data.id',
        $business->id
    );

    $response->assertJsonPath(
        'data.status',
        'pending'
    );
});

test('unverified user cannot access admin business detail', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'email_verified_at' => null,
    ]);

    $business = Business::factory()->create([
        'status' => 'pending',
        'is_verified' => false,
        'verified_at' => null,
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson(
            "/api/admin/businesses/{$business->id}"
        );

    $response->assertForbidden();
});

test('guest cannot access admin business list', function () {
    $response = $this->getJson('/api/admin/businesses');

    $response->assertUnauthorized();
});