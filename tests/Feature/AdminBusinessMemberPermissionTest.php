<?php

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\BusinessUserPermission;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createMemberPermissionAdmin(): User
{
    return User::factory()->create([
        'is_admin' => true,
        'email_verified_at' => now(),
    ]);
}

function createMemberPermissionUser(): User
{
    return User::factory()->create([
        'is_admin' => false,
        'email_verified_at' => now(),
    ]);
}

function createMemberPermissionMembership(
    Business $business
): BusinessUser {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    return BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'role' => 'staff',
        'is_active' => true,
    ]);
}

function createMemberPermission(): Permission
{
    return Permission::create([
        'name' => 'appointments.manage',
        'display_name' => 'Randevuları Yönet',
        'module' => 'appointments',
        'description' => 'Randevu oluşturma ve yönetme yetkisi.',
        'is_active' => true,
    ]);
}

test('admin can grant permission to business member', function () {
    $admin = createMemberPermissionAdmin();

    $business = Business::factory()->create();

    $membership = createMemberPermissionMembership($business);

    $permission = createMemberPermission();

    $response = $this->actingAs($admin, 'sanctum')
        ->putJson(
            "/api/admin/businesses/{$business->id}/members/{$membership->id}/permissions/{$permission->id}",
            [
                'is_allowed' => true,
            ]
        );

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Üye yetkisi başarıyla verildi.',
        ]);

    $response->assertJsonPath(
        'data.permission_assignment.is_allowed',
        true
    );

    $response->assertJsonPath(
        'data.permission_assignment.granted_by_user_id',
        $admin->id
    );

    $this->assertDatabaseHas('business_user_permission', [
        'business_id' => $business->id,
        'user_id' => $membership->user_id,
        'permission_id' => $permission->id,
        'granted_by_user_id' => $admin->id,
        'is_allowed' => true,
    ]);
});

test('admin can deny permission for business member', function () {
    $admin = createMemberPermissionAdmin();

    $business = Business::factory()->create();

    $membership = createMemberPermissionMembership($business);

    $permission = createMemberPermission();

    $response = $this->actingAs($admin, 'sanctum')
        ->putJson(
            "/api/admin/businesses/{$business->id}/members/{$membership->id}/permissions/{$permission->id}",
            [
                'is_allowed' => false,
            ]
        );

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Üye yetkisi başarıyla kaldırıldı.',
        ]);

    $response->assertJsonPath(
        'data.permission_assignment.is_allowed',
        false
    );

    $this->assertDatabaseHas('business_user_permission', [
        'business_id' => $business->id,
        'user_id' => $membership->user_id,
        'permission_id' => $permission->id,
        'granted_by_user_id' => $admin->id,
        'is_allowed' => false,
    ]);
});

test('admin can update an existing permission assignment without creating duplicate', function () {
    $admin = createMemberPermissionAdmin();

    $business = Business::factory()->create();

    $membership = createMemberPermissionMembership($business);

    $permission = createMemberPermission();

    BusinessUserPermission::create([
        'business_id' => $business->id,
        'user_id' => $membership->user_id,
        'permission_id' => $permission->id,
        'granted_by_user_id' => $admin->id,
        'is_allowed' => true,
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->putJson(
            "/api/admin/businesses/{$business->id}/members/{$membership->id}/permissions/{$permission->id}",
            [
                'is_allowed' => false,
            ]
        );

    $response->assertOk();

    expect(
        BusinessUserPermission::query()
            ->where('business_id', $business->id)
            ->where('user_id', $membership->user_id)
            ->where('permission_id', $permission->id)
            ->count()
    )->toBe(1);

    $this->assertDatabaseHas('business_user_permission', [
        'business_id' => $business->id,
        'user_id' => $membership->user_id,
        'permission_id' => $permission->id,
        'is_allowed' => false,
    ]);
});

test('admin can change permission assignment back to allowed', function () {
    $admin = createMemberPermissionAdmin();

    $business = Business::factory()->create();

    $membership = createMemberPermissionMembership($business);

    $permission = createMemberPermission();

    BusinessUserPermission::create([
        'business_id' => $business->id,
        'user_id' => $membership->user_id,
        'permission_id' => $permission->id,
        'granted_by_user_id' => $admin->id,
        'is_allowed' => false,
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->putJson(
            "/api/admin/businesses/{$business->id}/members/{$membership->id}/permissions/{$permission->id}",
            [
                'is_allowed' => true,
            ]
        );

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.permission_assignment.is_allowed',
            true
        );

    $this->assertDatabaseHas('business_user_permission', [
        'business_id' => $business->id,
        'user_id' => $membership->user_id,
        'permission_id' => $permission->id,
        'is_allowed' => true,
    ]);
});

test('admin cannot assign permission to membership belonging to another business', function () {
    $admin = createMemberPermissionAdmin();

    $business = Business::factory()->create();

    $otherBusiness = Business::factory()->create();

    $membership = createMemberPermissionMembership(
        $otherBusiness
    );

    $permission = createMemberPermission();

    $response = $this->actingAs($admin, 'sanctum')
        ->putJson(
            "/api/admin/businesses/{$business->id}/members/{$membership->id}/permissions/{$permission->id}",
            [
                'is_allowed' => true,
            ]
        );

    $response->assertNotFound();

    $this->assertDatabaseMissing('business_user_permission', [
        'business_id' => $business->id,
        'user_id' => $membership->user_id,
        'permission_id' => $permission->id,
    ]);
});

test('non admin user cannot change business member permission', function () {
    $user = createMemberPermissionUser();

    $business = Business::factory()->create();

    $membership = createMemberPermissionMembership($business);

    $permission = createMemberPermission();

    $response = $this->actingAs($user, 'sanctum')
        ->putJson(
            "/api/admin/businesses/{$business->id}/members/{$membership->id}/permissions/{$permission->id}",
            [
                'is_allowed' => true,
            ]
        );

    $response->assertForbidden();

    $this->assertDatabaseMissing('business_user_permission', [
        'business_id' => $business->id,
        'user_id' => $membership->user_id,
        'permission_id' => $permission->id,
    ]);
});

test('guest cannot change business member permission', function () {
    $business = Business::factory()->create();

    $membership = createMemberPermissionMembership($business);

    $permission = createMemberPermission();

    $response = $this->putJson(
        "/api/admin/businesses/{$business->id}/members/{$membership->id}/permissions/{$permission->id}",
        [
            'is_allowed' => true,
        ]
    );

    $response->assertUnauthorized();
});

test('admin cannot assign nonexistent permission', function () {
    $admin = createMemberPermissionAdmin();

    $business = Business::factory()->create();

    $membership = createMemberPermissionMembership($business);

    $response = $this->actingAs($admin, 'sanctum')
        ->putJson(
            "/api/admin/businesses/{$business->id}/members/{$membership->id}/permissions/999999",
            [
                'is_allowed' => true,
            ]
        );

    $response->assertNotFound();
});

test('admin cannot update nonexistent membership permission', function () {
    $admin = createMemberPermissionAdmin();

    $business = Business::factory()->create();

    $permission = createMemberPermission();

    $response = $this->actingAs($admin, 'sanctum')
        ->putJson(
            "/api/admin/businesses/{$business->id}/members/999999/permissions/{$permission->id}",
            [
                'is_allowed' => true,
            ]
        );

    $response->assertNotFound();
});

test('permission assignment stores the admin who granted it', function () {
    $admin = createMemberPermissionAdmin();

    $business = Business::factory()->create();

    $membership = createMemberPermissionMembership($business);

    $permission = createMemberPermission();

    $this->actingAs($admin, 'sanctum')
        ->putJson(
            "/api/admin/businesses/{$business->id}/members/{$membership->id}/permissions/{$permission->id}",
            [
                'is_allowed' => true,
            ]
        )
        ->assertOk();

    $assignment = BusinessUserPermission::query()
        ->where('business_id', $business->id)
        ->where('user_id', $membership->user_id)
        ->where('permission_id', $permission->id)
        ->first();

    expect($assignment)->not->toBeNull();
    expect($assignment->granted_by_user_id)->toBe($admin->id);
});