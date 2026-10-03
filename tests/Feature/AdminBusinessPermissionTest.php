<?php

use App\Models\Business;
use App\Models\BusinessUserPermission;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createPermissionAdmin(): User
{
    return User::factory()->create([
        'is_admin' => true,
        'email_verified_at' => now(),
    ]);
}

function createPermissionUser(): User
{
    return User::factory()->create([
        'is_admin' => false,
        'email_verified_at' => now(),
    ]);
}

test('admin can access business permissions', function () {
    $admin = createPermissionAdmin();

    $business = Business::factory()->create();

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $permission = Permission::create([
        'name' => 'appointments.view',
        'display_name' => 'Randevuları Görüntüle',
        'module' => 'appointments',
        'description' => 'Randevuları görüntüleme yetkisi.',
        'is_active' => true,
    ]);

    BusinessUserPermission::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'permission_id' => $permission->id,
        'granted_by_user_id' => $admin->id,
        'is_allowed' => true,
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson(
            "/api/admin/businesses/{$business->id}/permissions"
        );

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    expect($response->json('data.data'))
        ->toHaveCount(1);

    $response->assertJsonPath(
        'data.data.0.business_id',
        $business->id
    );

    $response->assertJsonPath(
        'data.data.0.user.id',
        $user->id
    );

    $response->assertJsonPath(
        'data.data.0.permission.id',
        $permission->id
    );
});

test('admin can see permission details', function () {
    $admin = createPermissionAdmin();

    $business = Business::factory()->create();

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $permission = Permission::create([
        'name' => 'appointments.manage',
        'display_name' => 'Randevu Yönetimi',
        'module' => 'appointments',
        'description' => 'Randevu yönetme yetkisi.',
        'is_active' => true,
    ]);

    BusinessUserPermission::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'permission_id' => $permission->id,
        'granted_by_user_id' => $admin->id,
        'is_allowed' => true,
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson(
            "/api/admin/businesses/{$business->id}/permissions"
        );

    $response->assertOk();

    $response->assertJsonPath(
        'data.data.0.permission.name',
        'appointments.manage'
    );

    $response->assertJsonPath(
        'data.data.0.permission.display_name',
        'Randevu Yönetimi'
    );

    $response->assertJsonPath(
        'data.data.0.permission.module',
        'appointments'
    );

    $response->assertJsonPath(
        'data.data.0.permission.description',
        'Randevu yönetme yetkisi.'
    );

    $response->assertJsonPath(
        'data.data.0.permission.is_active',
        true
    );
});

test('admin can see who granted the permission', function () {
    $admin = createPermissionAdmin();

    $business = Business::factory()->create();

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $permission = Permission::create([
        'name' => 'reports.view',
        'display_name' => 'Raporları Görüntüle',
        'module' => 'reports',
        'description' => 'Raporları görüntüleme yetkisi.',
        'is_active' => true,
    ]);

    BusinessUserPermission::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'permission_id' => $permission->id,
        'granted_by_user_id' => $admin->id,
        'is_allowed' => true,
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson(
            "/api/admin/businesses/{$business->id}/permissions"
        );

    $response->assertOk();

    $response->assertJsonPath(
        'data.data.0.granted_by.id',
        $admin->id
    );

    $response->assertJsonPath(
        'data.data.0.granted_by.name',
        $admin->name
    );

    $response->assertJsonPath(
        'data.data.0.granted_by.email',
        $admin->email
    );
});

test('admin only sees permissions belonging to requested business', function () {
    $admin = createPermissionAdmin();

    $business = Business::factory()->create();
    $otherBusiness = Business::factory()->create();

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $otherUser = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $permission = Permission::create([
        'name' => 'appointments.view',
        'display_name' => 'Randevuları Görüntüle',
        'module' => 'appointments',
        'description' => 'Randevuları görüntüleme yetkisi.',
        'is_active' => true,
    ]);

    $otherPermission = Permission::create([
        'name' => 'reports.view',
        'display_name' => 'Raporları Görüntüle',
        'module' => 'reports',
        'description' => 'Raporları görüntüleme yetkisi.',
        'is_active' => true,
    ]);

    BusinessUserPermission::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'permission_id' => $permission->id,
        'granted_by_user_id' => $admin->id,
        'is_allowed' => true,
    ]);

    BusinessUserPermission::create([
        'business_id' => $otherBusiness->id,
        'user_id' => $otherUser->id,
        'permission_id' => $otherPermission->id,
        'granted_by_user_id' => $admin->id,
        'is_allowed' => true,
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson(
            "/api/admin/businesses/{$business->id}/permissions"
        );

    $response->assertOk();

    expect($response->json('data.data'))
        ->toHaveCount(1);

    $response->assertJsonPath(
        'data.data.0.permission.id',
        $permission->id
    );
});

test('admin can see denied permissions', function () {
    $admin = createPermissionAdmin();

    $business = Business::factory()->create();

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $permission = Permission::create([
        'name' => 'finance.view',
        'display_name' => 'Finans Görüntüle',
        'module' => 'finance',
        'description' => 'Finans bilgilerini görüntüleme yetkisi.',
        'is_active' => true,
    ]);

    BusinessUserPermission::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'permission_id' => $permission->id,
        'granted_by_user_id' => $admin->id,
        'is_allowed' => false,
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson(
            "/api/admin/businesses/{$business->id}/permissions"
        );

    $response->assertOk();

    $response->assertJsonPath(
        'data.data.0.is_allowed',
        false
    );
});

test('admin can paginate business permissions', function () {
    $admin = createPermissionAdmin();

    $business = Business::factory()->create();

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    for ($i = 1; $i <= 25; $i++) {
        $permission = Permission::create([
            'name' => "module.permission.{$i}",
            'display_name' => "Yetki {$i}",
            'module' => 'module',
            'description' => "Test yetkisi {$i}.",
            'is_active' => true,
        ]);

        BusinessUserPermission::create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'permission_id' => $permission->id,
            'granted_by_user_id' => $admin->id,
            'is_allowed' => true,
        ]);
    }

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson(
            "/api/admin/businesses/{$business->id}/permissions?per_page=10"
        );

    $response->assertOk();

    expect($response->json('data.data'))
        ->toHaveCount(10);

    expect($response->json('data.per_page'))
        ->toBe(10);

    expect($response->json('data.total'))
        ->toBe(25);
});

test('permission per page cannot exceed 100', function () {
    $admin = createPermissionAdmin();

    $business = Business::factory()->create();

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    for ($i = 1; $i <= 105; $i++) {
        $permission = Permission::create([
            'name' => "module.permission.{$i}",
            'display_name' => "Yetki {$i}",
            'module' => 'module',
            'description' => "Test yetkisi {$i}.",
            'is_active' => true,
        ]);

        BusinessUserPermission::create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'permission_id' => $permission->id,
            'granted_by_user_id' => $admin->id,
            'is_allowed' => true,
        ]);
    }

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson(
            "/api/admin/businesses/{$business->id}/permissions?per_page=500"
        );

    $response->assertOk();

    expect($response->json('data.per_page'))
        ->toBe(100);

    expect($response->json('data.data'))
        ->toHaveCount(100);
});

test('non admin user cannot access business permissions', function () {
    $user = createPermissionUser();

    $business = Business::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson(
            "/api/admin/businesses/{$business->id}/permissions"
        );

    $response->assertForbidden();
});

test('guest cannot access business permissions', function () {
    $business = Business::factory()->create();

    $response = $this->getJson(
        "/api/admin/businesses/{$business->id}/permissions"
    );

    $response->assertUnauthorized();
});

test('admin cannot access permissions of a nonexistent business', function () {
    $admin = createPermissionAdmin();

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson(
            '/api/admin/businesses/999999/permissions'
        );

    $response->assertNotFound();
});