<?php

use App\Models\Business;
use App\Models\BusinessStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createStatusAdmin(): User
{
    return User::factory()->create([
        'is_admin' => true,
        'email_verified_at' => now(),
    ]);
}

function createStatusUser(): User
{
    return User::factory()->create([
        'is_admin' => false,
        'email_verified_at' => now(),
    ]);
}

test('admin can suspend an active business', function () {
    $admin = createStatusAdmin();

    $business = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
        'verified_at' => now(),
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson(
            "/api/admin/businesses/{$business->id}/suspend",
            [
                'reason' => 'Platform kurallarının ihlali nedeniyle geçici olarak askıya alındı.',
            ]
        );

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Klinik askıya alındı.',
        ]);

    $business->refresh();

    expect($business->status)
        ->toBe('suspended');

    $this->assertDatabaseHas('business_status_histories', [
        'business_id' => $business->id,
        'changed_by_user_id' => $admin->id,
        'previous_status' => 'active',
        'new_status' => 'suspended',
        'reason' => 'Platform kurallarının ihlali nedeniyle geçici olarak askıya alındı.',
    ]);
});

test('admin must provide a reason when suspending a business', function () {
    $admin = createStatusAdmin();

    $business = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
        'verified_at' => now(),
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson(
            "/api/admin/businesses/{$business->id}/suspend",
            []
        );

    $response->assertStatus(422);

    $business->refresh();

    expect($business->status)
        ->toBe('active');

    $this->assertDatabaseMissing('business_status_histories', [
        'business_id' => $business->id,
        'new_status' => 'suspended',
    ]);
});

test('admin cannot suspend a pending business', function () {
    $admin = createStatusAdmin();

    $business = Business::factory()->create([
        'status' => 'pending',
        'is_verified' => false,
        'verified_at' => null,
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson(
            "/api/admin/businesses/{$business->id}/suspend",
            [
                'reason' => 'Test askıya alma sebebi.',
            ]
        );

    $response->assertStatus(422);

    $business->refresh();

    expect($business->status)
        ->toBe('pending');

    $this->assertDatabaseMissing('business_status_histories', [
        'business_id' => $business->id,
        'new_status' => 'suspended',
    ]);
});

test('admin cannot suspend an already suspended business', function () {
    $admin = createStatusAdmin();

    $business = Business::factory()->create([
        'status' => 'suspended',
        'is_verified' => true,
        'verified_at' => now(),
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson(
            "/api/admin/businesses/{$business->id}/suspend",
            [
                'reason' => 'Tekrar askıya alma deneniyor.',
            ]
        );

    $response->assertStatus(422);

    $business->refresh();

    expect($business->status)
        ->toBe('suspended');

    $this->assertDatabaseMissing('business_status_histories', [
        'business_id' => $business->id,
        'new_status' => 'suspended',
    ]);
});

test('admin can reactivate a suspended business', function () {
    $admin = createStatusAdmin();

    $business = Business::factory()->create([
        'status' => 'suspended',
        'is_verified' => true,
        'verified_at' => now(),
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson(
            "/api/admin/businesses/{$business->id}/reactivate",
            [
                'reason' => 'Askıya alma sebebi giderildi ve klinik tekrar incelenerek aktifleştirildi.',
            ]
        );

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Klinik tekrar aktifleştirildi.',
        ]);

    $business->refresh();

    expect($business->status)
        ->toBe('active');

    $this->assertDatabaseHas('business_status_histories', [
        'business_id' => $business->id,
        'changed_by_user_id' => $admin->id,
        'previous_status' => 'suspended',
        'new_status' => 'active',
        'reason' => 'Askıya alma sebebi giderildi ve klinik tekrar incelenerek aktifleştirildi.',
    ]);
});

test('admin must provide a reason when reactivating a business', function () {
    $admin = createStatusAdmin();

    $business = Business::factory()->create([
        'status' => 'suspended',
        'is_verified' => true,
        'verified_at' => now(),
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson(
            "/api/admin/businesses/{$business->id}/reactivate",
            []
        );

    $response->assertStatus(422);

    $business->refresh();

    expect($business->status)
        ->toBe('suspended');

    $this->assertDatabaseMissing('business_status_histories', [
        'business_id' => $business->id,
        'new_status' => 'active',
    ]);
});

test('admin cannot reactivate an active business', function () {
    $admin = createStatusAdmin();

    $business = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
        'verified_at' => now(),
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson(
            "/api/admin/businesses/{$business->id}/reactivate",
            [
                'reason' => 'Aktif klinik tekrar aktifleştirilmeye çalışılıyor.',
            ]
        );

    $response->assertStatus(422);

    $business->refresh();

    expect($business->status)
        ->toBe('active');

    $this->assertDatabaseMissing('business_status_histories', [
        'business_id' => $business->id,
        'new_status' => 'active',
    ]);
});

test('admin cannot reactivate a rejected business', function () {
    $admin = createStatusAdmin();

    $business = Business::factory()->create([
        'status' => 'rejected',
        'is_verified' => false,
        'verified_at' => null,
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson(
            "/api/admin/businesses/{$business->id}/reactivate",
            [
                'reason' => 'Reddedilmiş klinik tekrar aktifleştirilmeye çalışılıyor.',
            ]
        );

    $response->assertStatus(422);

    $business->refresh();

    expect($business->status)
        ->toBe('rejected');

    $this->assertDatabaseMissing('business_status_histories', [
        'business_id' => $business->id,
        'new_status' => 'active',
    ]);
});

test('non admin user cannot suspend a business', function () {
    $user = createStatusUser();

    $business = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
        'verified_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(
            "/api/admin/businesses/{$business->id}/suspend",
            [
                'reason' => 'Yetkisiz askıya alma denemesi.',
            ]
        );

    $response->assertForbidden();

    $business->refresh();

    expect($business->status)
        ->toBe('active');

    $this->assertDatabaseMissing('business_status_histories', [
        'business_id' => $business->id,
    ]);
});

test('non admin user cannot reactivate a business', function () {
    $user = createStatusUser();

    $business = Business::factory()->create([
        'status' => 'suspended',
        'is_verified' => true,
        'verified_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(
            "/api/admin/businesses/{$business->id}/reactivate",
            [
                'reason' => 'Yetkisiz aktifleştirme denemesi.',
            ]
        );

    $response->assertForbidden();

    $business->refresh();

    expect($business->status)
        ->toBe('suspended');

    $this->assertDatabaseMissing('business_status_histories', [
        'business_id' => $business->id,
        'new_status' => 'active',
    ]);
});

test('guest cannot suspend a business', function () {
    $business = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
        'verified_at' => now(),
    ]);

    $response = $this->postJson(
        "/api/admin/businesses/{$business->id}/suspend",
        [
            'reason' => 'Misafir askıya alma denemesi.',
        ]
    );

    $response->assertUnauthorized();

    $business->refresh();

    expect($business->status)
        ->toBe('active');
});

test('guest cannot reactivate a business', function () {
    $business = Business::factory()->create([
        'status' => 'suspended',
        'is_verified' => true,
        'verified_at' => now(),
    ]);

    $response = $this->postJson(
        "/api/admin/businesses/{$business->id}/reactivate",
        [
            'reason' => 'Misafir aktifleştirme denemesi.',
        ]
    );

    $response->assertUnauthorized();

    $business->refresh();

    expect($business->status)
        ->toBe('suspended');
});