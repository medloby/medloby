<?php

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createMemberStatusAdmin(): User
{
    return User::factory()->create([
        'is_admin' => true,
        'email_verified_at' => now(),
    ]);
}

function createMemberStatusUser(): User
{
    return User::factory()->create([
        'is_admin' => false,
        'email_verified_at' => now(),
    ]);
}

function createMemberStatusMembership(
    Business $business,
    bool $isActive = true
): BusinessUser {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    return BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'role' => 'staff',
        'is_active' => $isActive,
    ]);
}

test('admin can deactivate active business member', function () {
    $admin = createMemberStatusAdmin();

    $business = Business::factory()->create();

    $membership = createMemberStatusMembership(
        $business,
        true
    );

    $response = $this->actingAs($admin, 'sanctum')
        ->putJson(
            "/api/admin/businesses/{$business->id}/members/{$membership->id}/status"
        );

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Üye başarıyla pasifleştirildi.',
        ]);

    $response->assertJsonPath(
        'data.previous_is_active',
        true
    );

    $response->assertJsonPath(
        'data.is_active',
        false
    );

    $this->assertDatabaseHas('business_user', [
        'id' => $membership->id,
        'business_id' => $business->id,
        'is_active' => false,
    ]);
});

test('admin can reactivate inactive business member', function () {
    $admin = createMemberStatusAdmin();

    $business = Business::factory()->create();

    $membership = createMemberStatusMembership(
        $business,
        false
    );

    $response = $this->actingAs($admin, 'sanctum')
        ->putJson(
            "/api/admin/businesses/{$business->id}/members/{$membership->id}/status"
        );

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Üye başarıyla aktifleştirildi.',
        ]);

    $response->assertJsonPath(
        'data.previous_is_active',
        false
    );

    $response->assertJsonPath(
        'data.is_active',
        true
    );

    $this->assertDatabaseHas('business_user', [
        'id' => $membership->id,
        'business_id' => $business->id,
        'is_active' => true,
    ]);
});

test('admin can toggle member status multiple times', function () {
    $admin = createMemberStatusAdmin();

    $business = Business::factory()->create();

    $membership = createMemberStatusMembership(
        $business,
        true
    );

    $firstResponse = $this->actingAs($admin, 'sanctum')
        ->putJson(
            "/api/admin/businesses/{$business->id}/members/{$membership->id}/status"
        );

    $firstResponse->assertOk();

    $this->assertDatabaseHas('business_user', [
        'id' => $membership->id,
        'is_active' => false,
    ]);

    $secondResponse = $this->actingAs($admin, 'sanctum')
        ->putJson(
            "/api/admin/businesses/{$business->id}/members/{$membership->id}/status"
        );

    $secondResponse
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Üye başarıyla aktifleştirildi.',
        ]);

    $this->assertDatabaseHas('business_user', [
        'id' => $membership->id,
        'is_active' => true,
    ]);
});

test('admin cannot change member status belonging to another business', function () {
    $admin = createMemberStatusAdmin();

    $business = Business::factory()->create();
    $otherBusiness = Business::factory()->create();

    $membership = createMemberStatusMembership(
        $otherBusiness,
        true
    );

    $response = $this->actingAs($admin, 'sanctum')
        ->putJson(
            "/api/admin/businesses/{$business->id}/members/{$membership->id}/status"
        );

    $response->assertNotFound();

    $this->assertDatabaseHas('business_user', [
        'id' => $membership->id,
        'business_id' => $otherBusiness->id,
        'is_active' => true,
    ]);
});

test('non admin user cannot change business member status', function () {
    $user = createMemberStatusUser();

    $business = Business::factory()->create();

    $membership = createMemberStatusMembership(
        $business,
        true
    );

    $response = $this->actingAs($user, 'sanctum')
        ->putJson(
            "/api/admin/businesses/{$business->id}/members/{$membership->id}/status"
        );

    $response->assertForbidden();

    $this->assertDatabaseHas('business_user', [
        'id' => $membership->id,
        'is_active' => true,
    ]);
});

test('guest cannot change business member status', function () {
    $business = Business::factory()->create();

    $membership = createMemberStatusMembership(
        $business,
        true
    );

    $response = $this->putJson(
        "/api/admin/businesses/{$business->id}/members/{$membership->id}/status"
    );

    $response->assertUnauthorized();

    $this->assertDatabaseHas('business_user', [
        'id' => $membership->id,
        'is_active' => true,
    ]);
});

test('admin cannot change status of a nonexistent membership', function () {
    $admin = createMemberStatusAdmin();

    $business = Business::factory()->create();

    $response = $this->actingAs($admin, 'sanctum')
        ->putJson(
            "/api/admin/businesses/{$business->id}/members/999999/status"
        );

    $response->assertNotFound();
});