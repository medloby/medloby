<?php

use App\Models\Branch;
use App\Models\CrmFollowUp;
use App\Models\CrmLead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createCrmReminderFollowUp(Branch $branch, array $attributes = [], array $leadAttributes = []): CrmFollowUp
{
    $lead = CrmLead::create(array_replace([
        'business_id' => $branch->business_id, 'branch_id' => $branch->id,
        'first_name' => 'Gizli Hasta Adı', 'status' => 'new',
    ], $leadAttributes));

    return CrmFollowUp::create(array_replace([
        'business_id' => $branch->business_id, 'branch_id' => $branch->id, 'crm_lead_id' => $lead->id,
        'title' => 'Kişiye özel takip başlığı', 'notes' => 'Özel takip notu',
        'scheduled_at' => now()->subMinute(), 'type' => 'call', 'status' => 'pending',
    ], $attributes));
}

test('the due reminder reaches its branch assignee once and contains no personal follow-up details', function () {
    $this->freezeTime();
    $branch = Branch::factory()->create();
    $otherBranch = Branch::factory()->for($branch->business)->create();
    $owner = User::factory()->create();
    $staff = User::factory()->unverified()->create(['email' => null, 'login_code' => 'deneme-personeli', 'staff_business_id' => $branch->business_id]);
    $otherStaff = User::factory()->create();
    $branch->business->memberships()->create(['user_id' => $owner->id, 'role' => 'business_owner', 'is_active' => true]);
    $membership = $branch->business->memberships()->create(['user_id' => $staff->id, 'role' => 'staff', 'is_active' => true]);
    $membership->branches()->attach($branch->id);
    $branch->business->memberships()->create(['user_id' => $otherStaff->id, 'role' => 'staff', 'is_active' => true])
        ->branches()->attach($otherBranch->id);

    $followUp = createCrmReminderFollowUp($branch, ['assigned_business_user_id' => $membership->id, 'scheduled_at' => now()]);
    foreach (['completed', 'cancelled'] as $status) {
        createCrmReminderFollowUp($branch, ['status' => $status]);
    }
    createCrmReminderFollowUp($branch, ['scheduled_at' => now()->addMinute()]);
    createCrmReminderFollowUp($branch, [], ['status' => 'completed']);
    createCrmReminderFollowUp($branch, [], ['status' => 'lost']);

    $this->artisan('medloby:remind-crm-follow-ups')->assertSuccessful();
    $this->artisan('medloby:remind-crm-follow-ups')->assertSuccessful();

    $this->assertDatabaseCount('notifications', 1);
    $this->actingAs($staff, 'sanctum')->getJson('/api/notifications/unread')
        ->assertOk()
        ->assertJsonPath('data.data.0.data', [
            'notification_type' => 'crm_follow_up_due',
            'title' => 'CRM takip saati geldi',
            'message' => 'Planlanan takibi CRM’de görüntüleyebilirsiniz.',
            'branch_id' => $branch->id,
            'crm_follow_up_id' => $followUp->id,
            'scheduled_at' => $followUp->scheduled_at->toISOString(),
        ]);
    $this->getJson("/api/branches/{$branch->id}/crm/follow-ups/{$followUp->id}")
        ->assertOk()->assertJsonPath('data.crm_lead_id', $followUp->crm_lead_id);
    $this->actingAs($otherStaff, 'sanctum')->getJson("/api/branches/{$branch->id}/crm/follow-ups/{$followUp->id}")
        ->assertForbidden();
});

test('unassigned or inaccessible assignments fall back to the owner once the clinic and branch are active', function () {
    $branch = Branch::factory()->create(['status' => 'inactive']);
    $branch->business->update(['status' => 'suspended']);
    $owner = User::factory()->create();
    $branch->business->memberships()->create(['user_id' => $owner->id, 'role' => 'business_owner', 'is_active' => true]);

    $foreignBranch = Branch::factory()->create();
    $foreign = $foreignBranch->business->memberships()->create(['user_id' => User::factory()->create()->id, 'role' => 'staff', 'is_active' => true]);
    $foreign->branches()->attach($foreignBranch->id);
    $unassigned = createCrmReminderFollowUp($branch);
    $unavailable = createCrmReminderFollowUp($branch, ['assigned_business_user_id' => $foreign->id]);

    $this->artisan('medloby:remind-crm-follow-ups')->assertSuccessful();
    $this->assertDatabaseCount('notifications', 0);

    $branch->business->update(['status' => 'active']);
    $this->artisan('medloby:remind-crm-follow-ups')->assertSuccessful();
    $this->assertDatabaseCount('notifications', 0);

    $branch->update(['status' => 'active']);
    $this->artisan('medloby:remind-crm-follow-ups')->assertSuccessful();
    $this->assertDatabaseCount('notifications', 2);
    expect($owner->notifications()->get()->pluck('data.crm_follow_up_id')->all())->toContain($unassigned->id, $unavailable->id);
});

test('only rescheduling or reassigning a previously notified follow-up enables a new reminder', function () {
    $this->freezeTime();
    $branch = Branch::factory()->create();
    $owner = User::factory()->create();
    $staff = User::factory()->create();
    $ownerMembership = $branch->business->memberships()->create(['user_id' => $owner->id, 'role' => 'business_owner', 'is_active' => true]);
    $membership = $branch->business->memberships()->create(['user_id' => $staff->id, 'role' => 'staff', 'is_active' => true]);
    $membership->branches()->attach($branch->id);
    $followUp = createCrmReminderFollowUp($branch);
    $route = "/api/branches/{$branch->id}/crm/follow-ups/{$followUp->id}";
    $this->actingAs($owner, 'sanctum');
    $this->artisan('medloby:remind-crm-follow-ups')->assertSuccessful();

    $this->putJson($route, ['title' => 'Düzenlenen takip', 'scheduled_at' => $followUp->scheduled_at->toISOString()])->assertOk();
    $this->artisan('medloby:remind-crm-follow-ups')->assertSuccessful();
    $this->assertDatabaseCount('notifications', 1);

    $this->putJson($route, [
        'scheduled_at' => now()->addMinutes(2)->toISOString(),
        'assigned_business_user_id' => $membership->id,
    ])->assertOk();
    $this->artisan('medloby:remind-crm-follow-ups')->assertSuccessful();
    $this->assertDatabaseCount('notifications', 1);

    $this->travel(2)->minutes();
    $this->artisan('medloby:remind-crm-follow-ups')->assertSuccessful();
    $this->assertDatabaseCount('notifications', 2);
    expect($staff->notifications()->count())->toBe(1);

    $this->putJson($route, ['assigned_business_user_id' => $ownerMembership->id])->assertOk();
    $this->artisan('medloby:remind-crm-follow-ups')->assertSuccessful();
    $this->assertDatabaseCount('notifications', 3);
    expect($owner->notifications()->count())->toBe(2);
});
