<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\BusinessUserPermission;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->business = Business::factory()->create();

    $this->otherBusiness = Business::factory()->create();

    $this->owner = User::factory()->create();

    $this->staff = User::factory()->create();

    $this->unauthorizedStaff = User::factory()->create();

    $this->ownerMembership = BusinessUser::create([
        'business_id' => $this->business->id,
        'user_id' => $this->owner->id,
        'role' => 'business_owner',
        'is_active' => true,
    ]);

    $this->staffMembership = BusinessUser::create([
        'business_id' => $this->business->id,
        'user_id' => $this->staff->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $this->unauthorizedStaffMembership = BusinessUser::create([
        'business_id' => $this->business->id,
        'user_id' => $this->unauthorizedStaff->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $this->branch = Branch::factory()->create([
        'business_id' => $this->business->id,
        'name' => 'Ana Şube',
        'slug' => 'ana-sube',
        'status' => 'active',
    ]);

    $this->secondBranch = Branch::factory()->create([
        'business_id' => $this->business->id,
        'name' => 'İkinci Şube',
        'slug' => 'ikinci-sube',
        'status' => 'active',
    ]);

    $this->otherBusinessBranch = Branch::factory()->create([
        'business_id' => $this->otherBusiness->id,
        'name' => 'Başka İşletme Şubesi',
        'slug' => 'baska-isletme-subesi',
        'status' => 'active',
    ]);

    $this->staffMembership->branches()->attach(
        $this->branch->id,
        [
            'is_active' => true,
        ]
    );

    $this->permission = Permission::create([
        'name' => 'branches.manage',
        'display_name' => 'Şube Yönetimi',
        'module' => 'branches',
        'description' => 'Şube yönetimi yetkisi.',
        'is_active' => true,
    ]);
});

test('business owner can list all business branches', function () {
    $response = $this->actingAs($this->owner)
        ->getJson(
            "/api/branches?business_id={$this->business->id}"
        );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    expect($response->json('data'))
        ->toHaveCount(2);
});

test('staff can list only assigned active branches', function () {
    $response = $this->actingAs($this->staff)
        ->getJson(
            "/api/branches?business_id={$this->business->id}"
        );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    expect($response->json('data'))
        ->toHaveCount(1)
        ->and($response->json('data.0.id'))
        ->toBe($this->branch->id);
});

test('business owner can create a branch', function () {
    $response = $this->actingAs($this->owner)
        ->postJson('/api/branches', [
            'business_id' => $this->business->id,
            'name' => 'Yeni Şube',
            'slug' => 'yeni-sube',
            'phone' => '05550000000',
            'email' => 'yeni@example.com',
            'city' => 'Manisa',
            'district' => 'Salihli',
            'address' => 'Yeni Şube Adresi',
            'status' => 'active',
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Yeni Şube')
        ->assertJsonPath('data.slug', 'yeni-sube');

    $this->assertDatabaseHas('branches', [
        'business_id' => $this->business->id,
        'name' => 'Yeni Şube',
        'slug' => 'yeni-sube',
        'status' => 'active',
    ]);
});

test('staff with branch management permission can create a branch', function () {
    BusinessUserPermission::create([
        'business_id' => $this->business->id,
        'user_id' => $this->staff->id,
        'permission_id' => $this->permission->id,
        'granted_by_user_id' => $this->owner->id,
        'is_allowed' => true,
    ]);

    $response = $this->actingAs($this->staff)
        ->postJson('/api/branches', [
            'business_id' => $this->business->id,
            'name' => 'Personel Tarafından Açılan Şube',
            'slug' => 'personel-acilan-sube',
            'status' => 'active',
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.name',
            'Personel Tarafından Açılan Şube'
        );
});

test('staff without branch management permission cannot create a branch', function () {
    $response = $this->actingAs($this->unauthorizedStaff)
        ->postJson('/api/branches', [
            'business_id' => $this->business->id,
            'name' => 'Yetkisiz Şube',
            'slug' => 'yetkisiz-sube',
        ]);

    $response
        ->assertForbidden()
        ->assertJsonPath(
            'message',
            'Şube yönetimi yetkiniz bulunmuyor.'
        );

    $this->assertDatabaseMissing('branches', [
        'business_id' => $this->business->id,
        'slug' => 'yetkisiz-sube',
    ]);
});

test('business owner can view a branch', function () {
    $response = $this->actingAs($this->owner)
        ->getJson(
            "/api/branches/{$this->branch->id}"
        );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath(
            'data.id',
            $this->branch->id
        );
});

test('staff can view an assigned branch', function () {
    $response = $this->actingAs($this->staff)
        ->getJson(
            "/api/branches/{$this->branch->id}"
        );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'data.id',
            $this->branch->id
        );
});

test('staff cannot view an unassigned branch', function () {
    $response = $this->actingAs($this->staff)
        ->getJson(
            "/api/branches/{$this->secondBranch->id}"
        );

    $response->assertForbidden();
});

test('business user cannot view another business branch', function () {
    $response = $this->actingAs($this->owner)
        ->getJson(
            "/api/branches/{$this->otherBusinessBranch->id}"
        );

    $response->assertForbidden();
});

test('business owner can update a branch', function () {
    $response = $this->actingAs($this->owner)
        ->putJson(
            "/api/branches/{$this->branch->id}",
            [
                'name' => 'Güncellenmiş Ana Şube',
                'slug' => 'guncellenmis-ana-sube',
                'city' => 'İzmir',
            ]
        );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'data.name',
            'Güncellenmiş Ana Şube'
        )
        ->assertJsonPath(
            'data.slug',
            'guncellenmis-ana-sube'
        );

    $this->assertDatabaseHas('branches', [
        'id' => $this->branch->id,
        'name' => 'Güncellenmiş Ana Şube',
        'slug' => 'guncellenmis-ana-sube',
    ]);
});

test('staff with branch management permission can update a branch', function () {
    BusinessUserPermission::create([
        'business_id' => $this->business->id,
        'user_id' => $this->staff->id,
        'permission_id' => $this->permission->id,
        'granted_by_user_id' => $this->owner->id,
        'is_allowed' => true,
    ]);

    $response = $this->actingAs($this->staff)
        ->putJson(
            "/api/branches/{$this->branch->id}",
            [
                'name' => 'Personel Güncelledi',
            ]
        );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'data.name',
            'Personel Güncelledi'
        );
});

test('staff without branch management permission cannot update a branch', function () {
    $response = $this->actingAs($this->unauthorizedStaff)
        ->putJson(
            "/api/branches/{$this->branch->id}",
            [
                'name' => 'Yetkisiz Güncelleme',
            ]
        );

    $response->assertForbidden();

    $this->assertDatabaseHas('branches', [
        'id' => $this->branch->id,
        'name' => 'Ana Şube',
    ]);
});

test('business owner can deactivate a branch without deleting it', function () {
    $response = $this->actingAs($this->owner)
        ->deleteJson(
            "/api/branches/{$this->branch->id}"
        );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath(
            'data.status',
            'inactive'
        );

    $this->assertDatabaseHas('branches', [
        'id' => $this->branch->id,
        'status' => 'inactive',
    ]);
});

test('duplicate branch slug is rejected within same business', function () {
    $response = $this->actingAs($this->owner)
        ->postJson('/api/branches', [
            'business_id' => $this->business->id,
            'name' => 'Başka Şube',
            'slug' => 'ana-sube',
        ]);

    $response->assertUnprocessable();

    expect($response->json('errors.slug'))
        ->not->toBeEmpty();
});

test('same branch slug can be used by another business', function () {
    $response = $this->actingAs($this->owner)
        ->postJson('/api/branches', [
            'business_id' => $this->business->id,
            'name' => 'Yeni Şube',
            'slug' => 'baska-isletme-subesi',
        ]);

    $response->assertCreated();
});