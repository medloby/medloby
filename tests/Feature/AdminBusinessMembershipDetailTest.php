<?php

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createMembershipDetailAdmin(): User
{
    return User::factory()->create([
        'is_admin' => true,
        'email_verified_at' => now(),
    ]);
}

function createMembershipDetailUser(): User
{
    return User::factory()->create([
        'is_admin' => false,
        'email_verified_at' => now(),
    ]);
}

test('admin can view business membership detail', function () {
    $admin = createMembershipDetailAdmin();

    $business = Business::factory()->create([
        'name' => 'ABC Diş Kliniği',
        'slug' => 'abc-dis-klinigi',
        'status' => 'active',
    ]);

    $user = User::factory()->create([
        'name' => 'Mehmet Yılmaz',
        'email' => 'mehmet@example.com',
        'email_verified_at' => now(),
    ]);

    $membership = BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson(
            "/api/admin/businesses/{$business->id}/members/{$membership->id}"
        );

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $response->assertJsonPath(
        'data.id',
        $membership->id
    );

    $response->assertJsonPath(
        'data.business_id',
        $business->id
    );

    $response->assertJsonPath(
        'data.user_id',
        $user->id
    );

    $response->assertJsonPath(
        'data.role',
        'staff'
    );

    $response->assertJsonPath(
        'data.is_active',
        true
    );
});

test('admin can see membership user details', function () {
    $admin = createMembershipDetailAdmin();

    $business = Business::factory()->create();

    $user = User::factory()->create([
        'name' => 'Ayşe Demir',
        'email' => 'ayse@example.com',
        'email_verified_at' => now(),
    ]);

    $membership = BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'role' => 'manager',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson(
            "/api/admin/businesses/{$business->id}/members/{$membership->id}"
        );

    $response->assertOk();

    $response->assertJsonPath(
        'data.user.id',
        $user->id
    );

    $response->assertJsonPath(
        'data.user.name',
        'Ayşe Demir'
    );

    $response->assertJsonPath(
        'data.user.email',
        'ayse@example.com'
    );

    $response->assertJsonPath(
        'data.business.id',
        $business->id
    );

    $response->assertJsonPath(
        'data.business.name',
        $business->name
    );

    $response->assertJsonPath(
        'data.business.slug',
        $business->slug
    );

    $response->assertJsonPath(
        'data.business.status',
        'active'
    );
});

test('admin can view inactive business membership', function () {
    $admin = createMembershipDetailAdmin();

    $business = Business::factory()->create();

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $membership = BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'role' => 'staff',
        'is_active' => false,
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson(
            "/api/admin/businesses/{$business->id}/members/{$membership->id}"
        );

    $response->assertOk();

    $response->assertJsonPath(
        'data.id',
        $membership->id
    );

    $response->assertJsonPath(
        'data.is_active',
        false
    );
});

test('admin cannot view membership belonging to another business', function () {
    $admin = createMembershipDetailAdmin();

    $business = Business::factory()->create();
    $otherBusiness = Business::factory()->create();

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $membership = BusinessUser::create([
        'business_id' => $otherBusiness->id,
        'user_id' => $user->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson(
            "/api/admin/businesses/{$business->id}/members/{$membership->id}"
        );

    $response->assertNotFound();
});

test('non admin user cannot view business membership detail', function () {
    $user = createMembershipDetailUser();

    $business = Business::factory()->create();

    $member = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $membership = BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $member->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson(
            "/api/admin/businesses/{$business->id}/members/{$membership->id}"
        );

    $response->assertForbidden();
});

test('guest cannot view business membership detail', function () {
    $business = Business::factory()->create();

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $membership = BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $response = $this->getJson(
        "/api/admin/businesses/{$business->id}/members/{$membership->id}"
    );

    $response->assertUnauthorized();
});

test('admin cannot view a nonexistent membership', function () {
    $admin = createMembershipDetailAdmin();

    $business = Business::factory()->create();

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson(
            "/api/admin/businesses/{$business->id}/members/999999"
        );

    $response->assertNotFound();
});