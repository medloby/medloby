<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\PlatformContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('a registered owner stays out of crm until verification and clinic approval are complete', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_admin' => true]);
    $contract = PlatformContract::create([
        'contract_type' => 'clinic_membership',
        'version' => 'crm-test-version',
        'title' => 'Klinik Üyelik Sözleşmesi',
        'content' => 'Test sözleşmesi.',
        'status' => 'published',
        'is_required' => true,
        'effective_at' => now()->subMinute(),
        'published_at' => now()->subMinute(),
    ]);

    $registration = $this->postJson('/api/register/business', [
        'owner_name' => 'Test Klinik Sahibi',
        'owner_email' => 'clinic-owner@example.test',
        'password' => 'test-password-123',
        'password_confirmation' => 'test-password-123',
        'business_name' => 'CRM Test Kliniği',
        'branch_name' => 'Merkez Şube',
        'city' => 'Ankara',
        'address' => 'Örnek Adres',
        'platform_contract_id' => $contract->id,
        'contract_accepted' => true,
    ])->assertCreated()->assertJsonPath('data.approval_status', 'pending');

    $businessId = $registration->json('data.business.id');
    $branchId = $registration->json('data.branch.id');
    $workspace = "/api/businesses/{$businessId}/crm";
    $branchCrm = "/api/branches/{$branchId}/crm";
    $owner = User::findOrFail($registration->json('data.user.id'));

    $this->actingAs($owner, 'sanctum')->getJson($workspace)->assertForbidden();

    $owner->markEmailAsVerified();
    $this->getJson('/api/clinic-applications')->assertOk()->assertJsonPath('data.0.crm_available', false);
    $this->getJson($workspace)->assertForbidden();
    foreach (['dashboard', 'leads', 'leads/assignees', 'pipeline-stages', 'tasks', 'follow-ups', 'activities'] as $endpoint) {
        $this->getJson("{$branchCrm}/{$endpoint}")->assertForbidden();
    }
    $this->postJson("{$branchCrm}/pipeline-stages", ['name' => 'Onay öncesi aşama'])->assertForbidden();
    $this->assertDatabaseMissing('crm_pipeline_stages', ['business_id' => $businessId]);

    $this->actingAs($admin, 'sanctum')->postJson("/api/admin/businesses/{$businessId}/approve")->assertOk();
    $client = $this->actingAs($owner, 'sanctum');
    $client->getJson('/api/clinic-applications')->assertOk()->assertJsonPath('data.0.crm_available', true);
    $client->getJson($workspace)->assertOk()->assertExactJson([
        'success' => true,
        'data' => [
            'name' => 'CRM Test Kliniği',
            'branches' => [['id' => $branchId, 'name' => 'Merkez Şube']],
            'can_manage_operations' => true,
        ],
    ]);
    $client->getJson("{$branchCrm}/dashboard")->assertOk()->assertJsonPath('data.summary.total_leads', 0);
    $task = $client->postJson("{$branchCrm}/tasks", ['title' => 'İlk CRM görevi'])->assertCreated();
    expect($task->json('data.created_by_business_user_id'))->toBe($registration->json('data.membership.id'));
});

test('inactive branch, suspended business and unapproved business deny crm access for the owner', function (string $state, bool $verified, string $branchState) {
    $owner = User::factory()->create();
    $business = Business::factory()->create(['status' => $state, 'is_verified' => $verified]);
    $branch = Branch::factory()->for($business)->create(['status' => $branchState]);
    $business->memberships()->create([
        'user_id' => $owner->id,
        'role' => 'business_owner',
        'is_active' => true,
    ]);

    $client = $this->actingAs($owner, 'sanctum');
    $client->getJson("/api/branches/{$branch->id}/crm/dashboard")->assertForbidden();
    if ($branchState === 'active') {
        $client->getJson("/api/businesses/{$business->id}/crm")->assertForbidden();
    }
})->with([
    'rejected clinic' => ['rejected', false, 'active'],
    'suspended clinic' => ['suspended', true, 'active'],
    'active clinic awaiting verification' => ['active', false, 'active'],
    'inactive branch' => ['active', true, 'inactive'],
]);

test('a guest and an owner from another clinic cannot access branch crm', function () {
    $branch = Branch::factory()->create();
    $workspace = "/api/businesses/{$branch->business_id}/crm";
    $branchCrm = "/api/branches/{$branch->id}/crm";

    $this->getJson($workspace)->assertUnauthorized();
    $this->getJson("{$branchCrm}/leads")->assertUnauthorized();
    $this->getJson("{$branchCrm}/leads/assignees")->assertUnauthorized();

    $otherOwner = User::factory()->create();
    Business::factory()->create()->memberships()->create([
        'user_id' => $otherOwner->id,
        'role' => 'business_owner',
        'is_active' => true,
    ]);

    $this->actingAs($otherOwner, 'sanctum')->getJson($workspace)->assertForbidden();
    $this->getJson("{$branchCrm}/leads")->assertForbidden();
    $this->getJson("{$branchCrm}/leads/assignees")->assertForbidden();
    $this->getJson("{$branchCrm}/pipeline-stages")->assertForbidden();
});

test('a clinic approval does not give an unverified owner access to crm', function () {
    $owner = User::factory()->unverified()->create();
    $branch = Branch::factory()->create();
    $branch->business->memberships()->create([
        'user_id' => $owner->id,
        'role' => 'business_owner',
        'is_active' => true,
    ]);

    $client = $this->actingAs($owner, 'sanctum');
    $client->getJson("/api/businesses/{$branch->business_id}/crm")->assertForbidden();
    $client->getJson("/api/branches/{$branch->id}/crm/dashboard")->assertForbidden();
});
