<?php

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a verified owner can see pending and approved decisions for their own applications', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);
    $pending = Business::factory()->create(['status' => 'pending', 'is_verified' => false]);
    $approved = Business::factory()->create(['status' => 'active']);
    $activeWithoutReview = Business::factory()->create(['status' => 'active']);

    foreach ([$pending, $approved, $activeWithoutReview] as $business) {
        $business->memberships()->create(['user_id' => $owner->id, 'role' => 'business_owner', 'is_active' => true]);
    }

    $review = $approved->reviews()->create([
        'reviewed_by_user_id' => $admin->id,
        'decision' => 'approved',
        'reviewed_at' => now()->subHour()->startOfSecond(),
        'new_status' => 'active',
    ]);

    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/clinic-applications')
        ->assertOk()
        ->assertExactJson([
            'success' => true,
            'data' => [
                [
                    'id' => $approved->id,
                    'name' => $approved->name,
                    'status' => 'approved',
                    'reviewed_at' => $review->reviewed_at->toISOString(),
                    'applicant_rejection_reason' => null,
                ],
                [
                    'id' => $pending->id,
                    'name' => $pending->name,
                    'status' => 'pending',
                    'reviewed_at' => null,
                    'applicant_rejection_reason' => null,
                ],
            ],
        ]);
});

test('an owner receives only the explanation explicitly provided for the applicant', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);
    $business = Business::factory()->create(['status' => 'pending', 'is_verified' => false]);
    $business->memberships()->create(['user_id' => $owner->id, 'role' => 'business_owner', 'is_active' => true]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/admin/businesses/'.$business->id.'/reject', [
            'rejection_reason' => 'İç değerlendirme için saklanan ayrıntılı gerekçe.',
            'notes' => 'Yöneticiye özel inceleme notu.',
            'applicant_rejection_reason' => 'Lütfen şubenin güncel ruhsat bilgilerini hazırlayın.',
        ]);
    $response->assertOk();

    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/clinic-applications')
        ->assertOk()
        ->assertExactJson([
            'success' => true,
            'data' => [
                [
                    'id' => $business->id,
                    'name' => $business->name,
                    'status' => 'rejected',
                    'reviewed_at' => $response->json('data.review.reviewed_at'),
                    'applicant_rejection_reason' => 'Lütfen şubenin güncel ruhsat bilgilerini hazırlayın.',
                ],
            ],
        ]);
});

test('older rejected applications never expose their internal reason to the owner', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);
    $business = Business::factory()->create(['status' => 'rejected', 'is_verified' => false]);
    $business->memberships()->create(['user_id' => $owner->id, 'role' => 'business_owner', 'is_active' => true]);
    $business->reviews()->create([
        'reviewed_by_user_id' => $admin->id,
        'decision' => 'rejected',
        'reviewed_at' => now(),
        'rejection_reason' => 'Daha önce kaydedilen iç değerlendirme.',
    ]);

    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/clinic-applications')
        ->assertOk()
        ->assertJsonPath('data.0.status', 'rejected')
        ->assertJsonPath('data.0.applicant_rejection_reason', null)
        ->assertJsonMissingPath('data.0.rejection_reason');
});

test('staff, inactive owners and users from another clinic get no application details', function () {
    $user = User::factory()->create();
    $staff = Business::factory()->create(['status' => 'rejected']);
    $inactive = Business::factory()->create(['status' => 'rejected']);
    Business::factory()->create(['status' => 'rejected']);

    $staff->memberships()->create(['user_id' => $user->id, 'role' => 'staff', 'is_active' => true]);
    $inactive->memberships()->create(['user_id' => $user->id, 'role' => 'business_owner', 'is_active' => false]);

    $this->actingAs($user, 'sanctum')->getJson('/api/clinic-applications')
        ->assertOk()
        ->assertExactJson(['success' => true, 'data' => []]);
});

test('a guest cannot view clinic applications', function () {
    $this->getJson('/api/clinic-applications')->assertUnauthorized();
});

test('an unverified owner cannot view clinic application details', function () {
    $owner = User::factory()->unverified()->create();
    $business = Business::factory()->create(['status' => 'rejected']);
    $business->memberships()->create(['user_id' => $owner->id, 'role' => 'business_owner', 'is_active' => true]);

    $this->actingAs($owner, 'sanctum')->getJson('/api/clinic-applications')->assertForbidden();
});
