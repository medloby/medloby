```php
<?php

use App\Models\Business;
use App\Models\BusinessStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createHistoryAdmin(): User
{
    return User::factory()->create([
        'is_admin' => true,
        'email_verified_at' => now(),
    ]);
}

function createHistoryUser(): User
{
    return User::factory()->create([
        'is_admin' => false,
        'email_verified_at' => now(),
    ]);
}

test('admin can access business status history', function () {
    $admin = createHistoryAdmin();

    $business = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
        'verified_at' => now(),
    ]);

    BusinessStatusHistory::create([
        'business_id' => $business->id,
        'changed_by_user_id' => $admin->id,
        'previous_status' => 'pending',
        'new_status' => 'active',
        'reason' => 'Klinik başvurusu onaylandı.',
        'changed_at' => now(),
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson(
            "/api/admin/businesses/{$business->id}/status-history"
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
});

test('admin can see status history with changed by user', function () {
    $admin = createHistoryAdmin();

    $business = Business::factory()->create([
        'status' => 'suspended',
    ]);

    BusinessStatusHistory::create([
        'business_id' => $business->id,
        'changed_by_user_id' => $admin->id,
        'previous_status' => 'active',
        'new_status' => 'suspended',
        'reason' => 'Geçici olarak askıya alındı.',
        'changed_at' => now(),
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson(
            "/api/admin/businesses/{$business->id}/status-history"
        );

    $response->assertOk();

    $response->assertJsonPath(
        'data.data.0.changed_by.id',
        $admin->id
    );

    $response->assertJsonPath(
        'data.data.0.changed_by.name',
        $admin->name
    );

    $response->assertJsonPath(
        'data.data.0.changed_by.email',
        $admin->email
    );
});

test('admin sees newest status history first', function () {
    $admin = createHistoryAdmin();

    $business = Business::factory()->create([
        'status' => 'active',
    ]);

    $older = BusinessStatusHistory::create([
        'business_id' => $business->id,
        'changed_by_user_id' => $admin->id,
        'previous_status' => 'pending',
        'new_status' => 'active',
        'reason' => 'Başvuru onaylandı.',
        'changed_at' => now()->subMinutes(10),
    ]);

    $newer = BusinessStatusHistory::create([
        'business_id' => $business->id,
        'changed_by_user_id' => $admin->id,
        'previous_status' => 'suspended',
        'new_status' => 'active',
        'reason' => 'Klinik tekrar aktifleştirildi.',
        'changed_at' => now(),
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson(
            "/api/admin/businesses/{$business->id}/status-history"
        );

    $response->assertOk();

    $response->assertJsonPath(
        'data.data.0.id',
        $newer->id
    );

    $response->assertJsonPath(
        'data.data.1.id',
        $older->id
    );
});

test('admin can paginate business status history', function () {
    $admin = createHistoryAdmin();

    $business = Business::factory()->create([
        'status' => 'active',
    ]);

    for ($i = 1; $i <= 25; $i++) {
        BusinessStatusHistory::create([
            'business_id' => $business->id,
            'changed_by_user_id' => $admin->id,
            'previous_status' => 'suspended',
            'new_status' => 'active',
            'reason' => "Durum değişikliği {$i}.",
            'changed_at' => now()->subMinutes($i),
        ]);
    }

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson(
            "/api/admin/businesses/{$business->id}/status-history?per_page=10"
        );

    $response->assertOk();

    expect($response->json('data.data'))
        ->toHaveCount(10);

    expect($response->json('data.per_page'))
        ->toBe(10);

    expect($response->json('data.total'))
        ->toBe(25);
});

test('status history per page cannot exceed 100', function () {
    $admin = createHistoryAdmin();

    $business = Business::factory()->create([
        'status' => 'active',
    ]);

    for ($i = 1; $i <= 105; $i++) {
        BusinessStatusHistory::create([
            'business_id' => $business->id,
            'changed_by_user_id' => $admin->id,
            'previous_status' => 'suspended',
            'new_status' => 'active',
            'reason' => "Durum değişikliği {$i}.",
            'changed_at' => now()->subMinutes($i),
        ]);
    }

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson(
            "/api/admin/businesses/{$business->id}/status-history?per_page=500"
        );

    $response->assertOk();

    expect($response->json('data.per_page'))
        ->toBe(100);

    expect($response->json('data.data'))
        ->toHaveCount(100);
});

test('non admin user cannot access business status history', function () {
    $user = createHistoryUser();

    $business = Business::factory()->create([
        'status' => 'active',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson(
            "/api/admin/businesses/{$business->id}/status-history"
        );

    $response->assertForbidden();
});

test('guest cannot access business status history', function () {
    $business = Business::factory()->create([
        'status' => 'active',
    ]);

    $response = $this->getJson(
        "/api/admin/businesses/{$business->id}/status-history"
    );

    $response->assertUnauthorized();
});

test('admin cannot access status history of a nonexistent business', function () {
    $admin = createHistoryAdmin();

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson(
            '/api/admin/businesses/999999/status-history'
        );

    $response->assertNotFound();
});
