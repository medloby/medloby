<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\PlatformContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('the clinic owner can add branches and restore inactive branches from the crm', function () {
    $business = Business::factory()->create(['country_code' => 'TR']);
    $branch = Branch::factory()->for($business)->create(['name' => 'Merkez Şube', 'slug' => 'merkez-sube']);
    $owner = User::factory()->create();
    $business->memberships()->create(['user_id' => $owner->id, 'role' => 'business_owner', 'is_active' => true]);
    $appointment = $branch->appointments()->create([
        'business_id' => $business->id, 'status' => 'confirmed',
        'starts_at' => '2026-12-04 09:00:00', 'ends_at' => '2026-12-04 09:30:00',
    ]);
    $workspace = "/api/businesses/{$business->id}/crm";
    $path = $workspace.'/branches';
    $directory = route('clinics.show', $business->slug);

    $this->getJson($path)->assertUnauthorized();
    $client = $this->actingAs($owner, 'sanctum');
    $client->getJson($path)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonCount(1, 'data.branches');
    $client->postJson($path, ['name' => ''])->assertUnprocessable()->assertJsonValidationErrors('name');
    $added = $client->postJson($path, ['name' => 'Merkez Şube', 'city' => 'İzmir', 'district' => 'Konak', 'address' => 'Prova adresi'])
        ->assertCreated()->assertJsonPath('data.status', 'active')->assertJsonPath('data.city', 'İzmir')->json('data.id');
    $this->assertDatabaseHas('branches', ['id' => $added, 'business_id' => $business->id,
        'slug' => 'merkez-sube-2', 'country_code' => 'TR', 'address' => 'Prova adresi']);
    $client->getJson($workspace)->assertOk()->assertJsonCount(2, 'data.branches');
    $client->get($directory)->assertOk()->assertSeeText('Prova adresi');

    $client->putJson($path.'/'.$added.'/status', ['status' => 'inactive'])->assertOk()->assertJsonPath('data.status', 'inactive');
    $client->putJson($path.'/'.$branch->id.'/status', ['status' => 'inactive'])->assertOk();
    $client->getJson($workspace)->assertOk()->assertJsonPath('data.can_manage_operations', true)->assertJsonCount(0, 'data.branches');
    $client->getJson($path)->assertOk()->assertJsonPath('data.branches.*.status', ['inactive', 'inactive']);
    $client->get($directory)->assertNotFound();
    $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'branch_id' => $branch->id, 'status' => 'confirmed']);

    $client->putJson($path.'/'.$added.'/status', ['status' => 'active'])->assertOk()->assertJsonPath('data.status', 'active');
    $client->getJson($workspace)->assertOk()->assertJsonCount(1, 'data.branches')->assertJsonPath('data.branches.0.id', $added);
    $client->get($directory)->assertOk()->assertSeeText('Prova adresi');
});

test('crm branch management is restricted to the approved clinic owner and scoped to that clinic', function () {
    $branch = Branch::factory()->create();
    $business = $branch->business;
    $foreign = Branch::factory()->create();
    $owner = User::factory()->create();
    $staff = User::factory()->create();
    $business->memberships()->create(['user_id' => $owner->id, 'role' => 'business_owner', 'is_active' => true]);
    $member = $business->memberships()->create(['user_id' => $staff->id, 'role' => 'staff', 'is_active' => true]);
    $member->branches()->attach($branch, ['permission_names' => ['branches.manage']]);
    $path = "/api/businesses/{$business->id}/crm/branches";

    $client = $this->actingAs($staff, 'sanctum');
    $client->getJson($path)->assertForbidden();
    $client->postJson($path, ['name' => 'Yetkisiz şube'])->assertForbidden();
    $client->putJson($path.'/'.$branch->id.'/status', ['status' => 'inactive'])->assertForbidden();

    $client = $this->actingAs($owner, 'sanctum');
    $client->putJson($path.'/'.$foreign->id.'/status', ['status' => 'inactive'])->assertNotFound();
    $client->putJson($path.'/'.$branch->id.'/status', ['status' => 'unknown'])->assertUnprocessable()->assertJsonValidationErrors('status');
    $business->update(['status' => 'suspended']);
    $client->getJson($path)->assertForbidden();
    $client->postJson($path, ['name' => 'Askıdaki şube'])->assertForbidden();
    $client->putJson($path.'/'.$branch->id.'/status', ['status' => 'inactive'])->assertForbidden();
    expect($branch->fresh()->status)->toBe('active');
    expect($foreign->fresh()->status)->toBe('active');
});

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
