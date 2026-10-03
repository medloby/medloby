<?php

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createMemberRoleAdmin(): User
{
    return User::factory()->create([
        'is_admin' => true,
        'email_verified_at' => now(),
    ]);
}

function createMemberRoleUser(): User
{
    return User::factory()->create([
        'is_admin' => false,
        'email_verified_at' => now(),
    ]);
}

function createMemberRoleMembership(
    Business $business,
    string $role = 'staff'
): BusinessUser {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    return BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'role' => $role,
        'is_active' => true,
    ]);
}

test('admin can change staff member role to manager', function () {
    $admin = createMemberRoleAdmin();

    $business = Business::factory()->create();

    $membership = createMemberRoleMembership(
        $business,
        'staff'
    );

    $response = $this->actingAs($admin, 'sanctum')
        ->putJson(
            "/api/admin/businesses/{$business->id}/members/{$membership->id}/role",
            [
                'role' => 'manager',
            ]
        );

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Üye rolü başarıyla güncellendi.',
        ]);

    $response->assertJsonPath(
        'data.previous_role',
        'staff'
    );

    $response->assertJsonPath(
        'data.new_role',
        'manager'
    );

    $response->assertJsonPath(
        'data.membership.role',
        'manager'
    );

    $this->assertDatabaseHas('business_user', [
        'id' => $membership->id,
        'business_id' => $business->id,
        'role' => 'manager',
    ]);
});

test('admin can change manager role to staff', function () {
    $admin = createMemberRoleAdmin();

    $business = Business::factory()->create();

    $membership = createMemberRoleMembership(
        $business,
        'manager'
    );

    $response = $this->actingAs($admin, 'sanctum')
        ->putJson(
            "/api/admin/businesses/{$business->id}/members/{$membership->id}/role",
            [
                'role' => 'staff',
            ]
        );

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $response->assertJsonPath(
        'data.previous_role',
        'manager'
    );

    $response->assertJsonPath(
        'data.new_role',
        'staff'
    );

    $this->assertDatabaseHas('business_user', [
        'id' => $membership->id,
        'role' => 'staff',
    ]);
});

test('admin can change staff member role to business owner', function () {
    $admin = createMemberRoleAdmin();

    $business = Business::factory()->create();

    $membership = createMemberRoleMembership(
        $business,
        'staff'
    );

    $response = $this->actingAs($admin, 'sanctum')
        ->putJson(
            "/api/admin/businesses/{$business->id}/members/{$membership->id}/role",
            [
                'role' => 'business_owner',
            ]
        );

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $response->assertJsonPath(
        'data.previous_role',
        'staff'
    );

    $response->assertJsonPath(
        'data.new_role',
        'business_owner'
    );

    $this->assertDatabaseHas('business_user', [
        'id' => $membership->id,
        'role' => 'business_owner',
    ]);
});

test('admin cannot assign an invalid member role', function () {
    $admin = createMemberRoleAdmin();

    $business = Business::factory()->create();

    $membership = createMemberRoleMembership(
        $business,
        'staff'
    );

    $response = $this->actingAs($admin, 'sanctum')
        ->putJson(
            "/api/admin/businesses/{$business->id}/members/{$membership->id}/role",
            [
                'role' => 'super_admin',
            ]
        );

    $response->assertUnprocessable();

    $this->assertDatabaseHas('business_user', [
        'id' => $membership->id,
        'role' => 'staff',
    ]);
});

test('admin cannot change member to the same role', function () {
    $admin = createMemberRoleAdmin();

    $business = Business::factory()->create();

    $membership = createMemberRoleMembership(
        $business,
        'manager'
    );

    $response = $this->actingAs($admin, 'sanctum')
        ->putJson(
            "/api/admin/businesses/{$business->id}/members/{$membership->id}/role",
            [
                'role' => 'manager',
            ]
        );

    $response
        ->assertUnprocessable()
        ->assertJson([
            'success' => false,
            'message' => 'Üyenin rolü zaten bu rolde.',
        ]);

    $this->assertDatabaseHas('business_user', [
        'id' => $membership->id,
        'role' => 'manager',
    ]);
});

test('admin cannot change membership belonging to another business', function () {
    $admin = createMemberRoleAdmin();

    $business = Business::factory()->create();
    $otherBusiness = Business::factory()->create();

    $membership = createMemberRoleMembership(
        $otherBusiness,
        'staff'
    );

    $response = $this->actingAs($admin, 'sanctum')
        ->putJson(
            "/api/admin/businesses/{$business->id}/members/{$membership->id}/role",
            [
                'role' => 'manager',
            ]
        );

    $response->assertNotFound();

    $this->assertDatabaseHas('business_user', [
        'id' => $membership->id,
        'business_id' => $otherBusiness->id,
        'role' => 'staff',
    ]);
});

test('non admin user cannot change member role', function () {
    $user = createMemberRoleUser();

    $business = Business::factory()->create();

    $membership = createMemberRoleMembership(
        $business,
        'staff'
    );

    $response = $this->actingAs($user, 'sanctum')
        ->putJson(
            "/api/admin/businesses/{$business->id}/members/{$membership->id}/role",
            [
                'role' => 'manager',
            ]
        );

    $response->assertForbidden();

    $this->assertDatabaseHas('business_user', [
        'id' => $membership->id,
        'role' => 'staff',
    ]);
});

test('guest cannot change member role', function () {
    $business = Business::factory()->create();

    $membership = createMemberRoleMembership(
        $business,
        'staff'
    );

    $response = $this->putJson(
        "/api/admin/businesses/{$business->id}/members/{$membership->id}/role",
        [
            'role' => 'manager',
        ]
    );

    $response->assertUnauthorized();

    $this->assertDatabaseHas('business_user', [
        'id' => $membership->id,
        'role' => 'staff',
    ]);
});

test('admin cannot change a nonexistent membership role', function () {
    $admin = createMemberRoleAdmin();

    $business = Business::factory()->create();

    $response = $this->actingAs($admin, 'sanctum')
        ->putJson(
            "/api/admin/businesses/{$business->id}/members/999999/role",
            [
                'role' => 'manager',
            ]
        );

    $response->assertNotFound();
});