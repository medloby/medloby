<?php

use App\Models\PlatformContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createAdminUser(): User
{
    return User::factory()->create([
        'is_admin' => true,
        'email_verified_at' => now(),
    ]);
}

function createRegularUser(): User
{
    return User::factory()->create([
        'is_admin' => false,
        'email_verified_at' => now(),
    ]);
}

function createContract(array $attributes = []): PlatformContract
{
    return PlatformContract::create(array_merge([
        'contract_type' => 'clinic_membership',
        'title' => 'Medloby Klinik Üyelik Sözleşmesi',
        'version' => '1.0',
        'content' => 'Test sözleşme metni.',
        'status' => 'draft',
        'is_required' => true,
        'effective_at' => null,
        'published_at' => null,
        'expires_at' => null,
    ], $attributes));
}

test('admin can access platform contract list', function () {
    $admin = createAdminUser();

    createContract();

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/platform-contracts');

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    expect($response->json('data.data'))
        ->toHaveCount(1);
});

test('admin can view platform contract detail', function () {
    $admin = createAdminUser();

    $contract = createContract();

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson(
            "/api/admin/platform-contracts/{$contract->id}"
        );

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $response->assertJsonPath(
        'data.id',
        $contract->id
    );

    $response->assertJsonPath(
        'data.version',
        '1.0'
    );
});

test('admin can create a platform contract as draft', function () {
    $admin = createAdminUser();

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/admin/platform-contracts', [
            'contract_type' => 'clinic_membership',
            'title' => 'Medloby Klinik Üyelik Sözleşmesi',
            'version' => '1.0',
            'content' => 'Test sözleşme metni.',
            'is_required' => true,
        ]);

    $response
        ->assertCreated()
        ->assertJson([
            'success' => true,
            'message' => 'Platform sözleşmesi taslak olarak oluşturuldu.',
        ]);

    $this->assertDatabaseHas('platform_contracts', [
        'contract_type' => 'clinic_membership',
        'version' => '1.0',
        'status' => 'draft',
        'is_required' => true,
        'created_by_user_id' => $admin->id,
        'updated_by_user_id' => $admin->id,
    ]);
});

test('admin cannot create duplicate contract version', function () {
    $admin = createAdminUser();

    createContract([
        'contract_type' => 'clinic_membership',
        'version' => '1.0',
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/admin/platform-contracts', [
            'contract_type' => 'clinic_membership',
            'title' => 'Aynı Versiyon',
            'version' => '1.0',
            'content' => 'Yeni içerik.',
        ]);

    $response->assertStatus(422);

    expect(
        PlatformContract::where(
            'contract_type',
            'clinic_membership'
        )->where('version', '1.0')->count()
    )->toBe(1);
});

test('admin can update a draft platform contract', function () {
    $admin = createAdminUser();

    $contract = createContract();

    $response = $this->actingAs($admin, 'sanctum')
        ->putJson(
            "/api/admin/platform-contracts/{$contract->id}",
            [
                'title' => 'Güncellenmiş Klinik Sözleşmesi',
                'content' => 'Güncellenmiş sözleşme içeriği.',
            ]
        );

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Platform sözleşmesi güncellendi.',
        ]);

    $contract->refresh();

    expect($contract->title)
        ->toBe('Güncellenmiş Klinik Sözleşmesi');

    expect($contract->content)
        ->toBe('Güncellenmiş sözleşme içeriği.');

    expect($contract->updated_by_user_id)
        ->toBe($admin->id);
});

test('admin cannot update a published platform contract', function () {
    $admin = createAdminUser();

    $contract = createContract([
        'status' => 'published',
        'published_at' => now(),
        'effective_at' => now(),
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->putJson(
            "/api/admin/platform-contracts/{$contract->id}",
            [
                'title' => 'Değiştirilmemesi gereken sözleşme',
            ]
        );

    $response->assertStatus(422);

    $contract->refresh();

    expect($contract->title)
        ->toBe('Medloby Klinik Üyelik Sözleşmesi');
});

test('admin can publish a draft platform contract', function () {
    $admin = createAdminUser();

    $contract = createContract();

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson(
            "/api/admin/platform-contracts/{$contract->id}/publish"
        );

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Platform sözleşmesi yayınlandı.',
        ]);

    $contract->refresh();

    expect($contract->status)
        ->toBe('published');

    expect($contract->published_at)
        ->not->toBeNull();

    expect($contract->effective_at)
        ->not->toBeNull();

    expect($contract->updated_by_user_id)
        ->toBe($admin->id);
});

test('admin cannot publish an already published contract', function () {
    $admin = createAdminUser();

    $contract = createContract([
        'status' => 'published',
        'published_at' => now(),
        'effective_at' => now(),
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson(
            "/api/admin/platform-contracts/{$contract->id}/publish"
        );

    $response->assertStatus(422);
});

test('admin can deactivate a published platform contract', function () {
    $admin = createAdminUser();

    $contract = createContract([
        'status' => 'published',
        'published_at' => now(),
        'effective_at' => now(),
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson(
            "/api/admin/platform-contracts/{$contract->id}/deactivate"
        );

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Platform sözleşmesi pasifleştirildi.',
        ]);

    $contract->refresh();

    expect($contract->status)
        ->toBe('inactive');

    expect($contract->updated_by_user_id)
        ->toBe($admin->id);
});

test('admin cannot deactivate a draft contract', function () {
    $admin = createAdminUser();

    $contract = createContract([
        'status' => 'draft',
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson(
            "/api/admin/platform-contracts/{$contract->id}/deactivate"
        );

    $response->assertStatus(422);

    $contract->refresh();

    expect($contract->status)
        ->toBe('draft');
});

test('admin cannot deactivate an already inactive contract', function () {
    $admin = createAdminUser();

    $contract = createContract([
        'status' => 'inactive',
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson(
            "/api/admin/platform-contracts/{$contract->id}/deactivate"
        );

    $response->assertStatus(422);
});

test('non admin user cannot access platform contract list', function () {
    $user = createRegularUser();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/admin/platform-contracts');

    $response
        ->assertStatus(403)
        ->assertJson([
            'success' => false,
            'message' => 'Bu işlem için admin yetkisi gereklidir.',
        ]);
});

test('non admin user cannot create platform contract', function () {
    $user = createRegularUser();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/admin/platform-contracts', [
            'contract_type' => 'clinic_membership',
            'title' => 'Yetkisiz Sözleşme',
            'version' => '1.0',
            'content' => 'Test içeriği.',
        ]);

    $response->assertForbidden();

    $this->assertDatabaseMissing('platform_contracts', [
        'title' => 'Yetkisiz Sözleşme',
    ]);
});

test('guest cannot access platform contract list', function () {
    $response = $this->getJson(
        '/api/admin/platform-contracts'
    );

    $response->assertUnauthorized();
});