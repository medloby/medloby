<?php

use App\Models\Business;
use App\Models\BusinessReview;
use App\Models\ClinicContractAcceptance;
use App\Models\PlatformContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('non admin user cannot access admin business list', function () {
    $user = User::factory()->create([
        'is_admin' => false,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/admin/businesses');

    $response
        ->assertStatus(403)
        ->assertJson([
            'success' => false,
            'message' => 'Bu işlem için admin yetkisi gereklidir.',
        ]);
});

test('admin can access pending business applications', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'email_verified_at' => now(),
    ]);

    $pendingBusiness = Business::factory()->create([
        'status' => 'pending',
        'is_verified' => false,
        'verified_at' => null,
    ]);

    Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
        'verified_at' => now(),
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/businesses');

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $response->assertJsonPath(
        'data.data.0.id',
        $pendingBusiness->id
    );

    expect($response->json('data.data'))
        ->toHaveCount(1);
});

test('admin can view a business application detail', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'email_verified_at' => now(),
    ]);

    $business = Business::factory()->create([
        'status' => 'pending',
        'is_verified' => false,
        'verified_at' => null,
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson(
            "/api/admin/businesses/{$business->id}"
        );

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $response->assertJsonPath(
        'data.id',
        $business->id
    );

    $response->assertJsonPath(
        'data.status',
        'pending'
    );
});

test('unverified user cannot access admin business detail', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'email_verified_at' => null,
    ]);

    $business = Business::factory()->create([
        'status' => 'pending',
        'is_verified' => false,
        'verified_at' => null,
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson(
            "/api/admin/businesses/{$business->id}"
        );

    $response->assertForbidden();
});

test('guest cannot access admin business list', function () {
    $response = $this->getJson('/api/admin/businesses');

    $response->assertUnauthorized();
});

test('admin can approve pending business with accepted contract', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'email_verified_at' => now(),
    ]);

    $owner = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $business = Business::factory()->create([
        'status' => 'pending',
        'is_verified' => false,
        'verified_at' => null,
    ]);

    DB::table('business_user')->insert([
        'business_id' => $business->id,
        'user_id' => $owner->id,
        'role' => 'business_owner',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('branches')->insert([
        'business_id' => $business->id,
        'name' => $business->name,
        'slug' => 'merkez',
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $contract = PlatformContract::create([
        'contract_type' => 'clinic_membership',
        'title' => 'Medloby Klinik Üyelik Sözleşmesi',
        'version' => '1.0',
        'content' => 'Test sözleşme metni.',
        'status' => 'published',
        'is_required' => true,
        'effective_at' => now(),
        'published_at' => now(),
    ]);

    ClinicContractAcceptance::create([
        'business_id' => $business->id,
        'user_id' => $owner->id,
        'platform_contract_id' => $contract->id,
        'contract_version' => $contract->version,
        'accepted_at' => now(),
        'acceptance_method' => 'checkbox',
        'is_accepted' => true,
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson(
            "/api/admin/businesses/{$business->id}/approve"
        );

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Klinik başvurusu başarıyla onaylandı.',
        ]);

    $business->refresh();

    expect($business->status)
        ->toBe('active');

    expect($business->is_verified)
        ->toBeTrue();

    expect($business->verified_at)
        ->not->toBeNull();

    $this->assertDatabaseHas('branches', [
        'business_id' => $business->id,
        'status' => 'active',
    ]);

    $this->assertDatabaseHas('business_reviews', [
        'business_id' => $business->id,
        'reviewed_by_user_id' => $admin->id,
        'decision' => 'approved',
        'previous_status' => 'pending',
        'new_status' => 'active',
    ]);
});

test('admin cannot approve business without accepted contract', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'email_verified_at' => now(),
    ]);

    $business = Business::factory()->create([
        'status' => 'pending',
        'is_verified' => false,
        'verified_at' => null,
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson(
            "/api/admin/businesses/{$business->id}/approve"
        );

    $response->assertStatus(422);

    $business->refresh();

    expect($business->status)
        ->toBe('pending');

    expect($business->is_verified)
        ->toBeFalse();

    expect($business->verified_at)
        ->toBeNull();

    $this->assertDatabaseMissing('business_reviews', [
        'business_id' => $business->id,
        'decision' => 'approved',
    ]);
});

test('admin can reject pending business with rejection reason', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'email_verified_at' => now(),
    ]);

    $business = Business::factory()->create([
        'status' => 'pending',
        'is_verified' => false,
        'verified_at' => null,
    ]);

    DB::table('branches')->insert([
        'business_id' => $business->id,
        'name' => $business->name,
        'slug' => 'merkez',
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson(
            "/api/admin/businesses/{$business->id}/reject",
            [
                'rejection_reason' => 'Başvuru belgeleri yeterli ve doğrulanabilir değildir.',
                'notes' => 'Ek belge talep edilmelidir.',
            ]
        );

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Klinik başvurusu reddedildi.',
        ]);

    $business->refresh();

    expect($business->status)
        ->toBe('rejected');

    expect($business->is_verified)
        ->toBeFalse();

    expect($business->verified_at)
        ->toBeNull();

    $this->assertDatabaseHas('branches', [
        'business_id' => $business->id,
        'status' => 'rejected',
    ]);

    $this->assertDatabaseHas('business_reviews', [
        'business_id' => $business->id,
        'reviewed_by_user_id' => $admin->id,
        'decision' => 'rejected',
        'rejection_reason' => 'Başvuru belgeleri yeterli ve doğrulanabilir değildir.',
        'previous_status' => 'pending',
        'new_status' => 'rejected',
    ]);
});

test('admin cannot reject pending business without rejection reason', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'email_verified_at' => now(),
    ]);

    $business = Business::factory()->create([
        'status' => 'pending',
        'is_verified' => false,
        'verified_at' => null,
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson(
            "/api/admin/businesses/{$business->id}/reject",
            []
        );

    $response->assertStatus(422);

    $business->refresh();

    expect($business->status)
        ->toBe('pending');

    $this->assertDatabaseMissing('business_reviews', [
        'business_id' => $business->id,
        'decision' => 'rejected',
    ]);
});

test('non admin user cannot approve business', function () {
    $user = User::factory()->create([
        'is_admin' => false,
        'email_verified_at' => now(),
    ]);

    $business = Business::factory()->create([
        'status' => 'pending',
        'is_verified' => false,
        'verified_at' => null,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(
            "/api/admin/businesses/{$business->id}/approve"
        );

    $response->assertForbidden();

    $business->refresh();

    expect($business->status)
        ->toBe('pending');
});

test('non admin user cannot reject business', function () {
    $user = User::factory()->create([
        'is_admin' => false,
        'email_verified_at' => now(),
    ]);

    $business = Business::factory()->create([
        'status' => 'pending',
        'is_verified' => false,
        'verified_at' => null,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(
            "/api/admin/businesses/{$business->id}/reject",
            [
                'rejection_reason' => 'Bu işlem için admin yetkisi bulunmamaktadır.',
            ]
        );

    $response->assertForbidden();

    $business->refresh();

    expect($business->status)
        ->toBe('pending');
});

test('admin cannot approve an already rejected business', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'email_verified_at' => now(),
    ]);

    $business = Business::factory()->create([
        'status' => 'rejected',
        'is_verified' => false,
        'verified_at' => null,
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson(
            "/api/admin/businesses/{$business->id}/approve"
        );

    $response->assertStatus(422);

    $this->assertDatabaseMissing('business_reviews', [
        'business_id' => $business->id,
        'decision' => 'approved',
    ]);
});

test('admin cannot reject an already active business', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'email_verified_at' => now(),
    ]);

    $business = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
        'verified_at' => now(),
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson(
            "/api/admin/businesses/{$business->id}/reject",
            [
                'rejection_reason' => 'Aktif bir kliniğin tekrar reddedilmesi deneniyor.',
            ]
        );

    $response->assertStatus(422);

    $this->assertDatabaseMissing('business_reviews', [
        'business_id' => $business->id,
        'decision' => 'rejected',
    ]);
});