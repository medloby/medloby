<?php

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createMembershipAdmin(): User
{
    return User::factory()->create([
        'is_admin' => true,
        'email_verified_at' => now(),
    ]);
}

function createMembershipUser(): User
{
    return User::factory()->create([
        'is_admin' => false,
        'email_verified_at' => now(),
    ]);
}

test('admin can access business memberships', function () {
    $admin = createMembershipAdmin();

    $business = Business::factory()->create();

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson(
            "/api/admin/businesses/{$business->id}/members"
        );

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    expect($response->json('data.data'))
        ->toHaveCount(1);

    $response->assertJsonPath(
        'data.data.0.user.id',
        $user->id
    );
});

test('admin can see membership user information', function () {
    $admin = createMembershipAdmin();

    $business = Business::factory()->create();

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'role' => 'manager',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson(
            "/api/admin/businesses/{$business->id}/members"
        );

    $response->assertOk();

    $response->assertJsonPath(
        'data.data.0.user.id',
        $user->id
    );

    $response->assertJsonPath(
        'data.data.0.user.name',
        $user->name
    );

    $response->assertJsonPath(
        'data.data.0.user.email',
        $user->email
    );

    $response->assertJsonPath(
        'data.data.0.role',
        'manager'
    );

    $response->assertJsonPath(
        'data.data.0.is_active',
        true
    );
});

test('admin only sees memberships belonging to requested business', function () {
    $admin = createMembershipAdmin();

    $business = Business::factory()->create();
    $otherBusiness = Business::factory()->create();

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $otherUser = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    BusinessUser::create([
        'business_id' => $otherBusiness->id,
        'user_id' => $otherUser->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson(
            "/api/admin/businesses/{$business->id}/members"
        );

    $response->assertOk();

    expect($response->json('data.data'))
        ->toHaveCount(1);

    $response->assertJsonPath(
        'data.data.0.user.id',
        $user->id
    );
});

test('admin can paginate business memberships', function () {
    $admin = createMembershipAdmin();

    $business = Business::factory()->create();

    for ($i = 1; $i <= 25; $i++) {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        BusinessUser::create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'role' => 'staff',
            'is_active' => true,
        ]);
    }

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson(
            "/api/admin/businesses/{$business->id}/members?per_page=10"
        );

    $response->assertOk();

    expect($response->json('data.data'))
        ->toHaveCount(10);

    expect($response->json('data.per_page'))
        ->toBe(10);

    expect($response->json('data.total'))
        ->toBe(25);
});

test('membership per page cannot exceed 100', function () {
    $admin = createMembershipAdmin();

    $business = Business::factory()->create();

    for ($i = 1; $i <= 105; $i++) {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        BusinessUser::create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'role' => 'staff',
            'is_active' => true,
        ]);
    }

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson(
            "/api/admin/businesses/{$business->id}/members?per_page=500"
        );

    $response->assertOk();

    expect($response->json('data.per_page'))
        ->toBe(100);

    expect($response->json('data.data'))
        ->toHaveCount(100);
});

test('non admin user cannot access business memberships', function () {
    $user = createMembershipUser();

    $business = Business::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson(
            "/api/admin/businesses/{$business->id}/members"
        );

    $response->assertForbidden();
});

test('guest cannot access business memberships', function () {
    $business = Business::factory()->create();

    $response = $this->getJson(
        "/api/admin/businesses/{$business->id}/members"
    );

    $response->assertUnauthorized();
});

test('admin cannot access memberships of a nonexistent business', function () {
    $admin = createMembershipAdmin();

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson(
            '/api/admin/businesses/999999/members'
        );

    $response->assertNotFound();
});