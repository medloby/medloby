<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\CrmLead;
use App\Models\CrmTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->business = Business::create([
        'name' => 'Test Klinik',
        'slug' => 'test-klinik-' . uniqid(),
    ]);

    $this->branch = Branch::create([
        'business_id' => $this->business->id,
        'name' => 'Merkez Şube',
        'slug' => 'merkez-sube-' . uniqid(),
        'status' => 'active',
    ]);

    $this->otherBranch = Branch::create([
        'business_id' => $this->business->id,
        'name' => 'İkinci Şube',
        'slug' => 'ikinci-sube-' . uniqid(),
        'status' => 'active',
    ]);

    $this->user = User::factory()->create();

    $this->businessUser = BusinessUser::create([
        'business_id' => $this->business->id,
        'user_id' => $this->user->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $this->businessUser->branches()->attach(
        $this->branch->id,
        [
            'is_active' => true,
        ]
    );

    $this->actingAs($this->user);
});

test('can list crm tasks for branch', function () {
    $task = CrmTask::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'created_by_business_user_id' => $this->businessUser->id,
        'title' => 'Hastayı ara',
        'type' => 'call',
        'status' => 'pending',
        'priority' => 'high',
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/tasks"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath(
            'data.0.id',
            $task->id
        )
        ->assertJsonPath(
            'data.0.title',
            'Hastayı ara'
        );
});

test('cannot list tasks belonging to another branch', function () {
    CrmTask::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->otherBranch->id,
        'created_by_business_user_id' => $this->businessUser->id,
        'title' => 'Diğer şube görevi',
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/tasks"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(0, 'data');
});

test('can create crm task', function () {
    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/tasks",
        [
            'title' => 'Hastayı ara',
            'description' => 'Saç ekimi hakkında bilgi verilecek.',
            'type' => 'call',
            'status' => 'pending',
            'priority' => 'high',
            'due_at' => '2026-10-08 14:00:00',
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath(
            'data.business_id',
            $this->business->id
        )
        ->assertJsonPath(
            'data.branch_id',
            $this->branch->id
        )
        ->assertJsonPath(
            'data.created_by_business_user_id',
            $this->businessUser->id
        )
        ->assertJsonPath(
            'data.title',
            'Hastayı ara'
        )
        ->assertJsonPath(
            'data.type',
            'call'
        )
        ->assertJsonPath(
            'data.status',
            'pending'
        )
        ->assertJsonPath(
            'data.priority',
            'high'
        );

    $this->assertDatabaseHas('crm_tasks', [
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'created_by_business_user_id' => $this->businessUser->id,
        'title' => 'Hastayı ara',
        'type' => 'call',
        'status' => 'pending',
        'priority' => 'high',
    ]);
});

test('crm task uses correct default values', function () {
    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/tasks",
        [
            'title' => 'Takip görevi',
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.type',
            'other'
        )
        ->assertJsonPath(
            'data.status',
            'pending'
        )
        ->assertJsonPath(
            'data.priority',
            'normal'
        );
});

test('can create crm task linked to lead', function () {
    $lead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'first_name' => 'Ahmet',
        'last_name' => 'Yılmaz',
        'status' => 'new',
    ]);

    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/tasks",
        [
            'crm_lead_id' => $lead->id,
            'title' => 'Lead ile görüş',
            'type' => 'follow_up',
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.crm_lead_id',
            $lead->id
        )
        ->assertJsonPath(
            'data.lead.id',
            $lead->id
        );
});

test('can assign crm task to business user', function () {
    $assignedUser = User::factory()->create();

    $assignedBusinessUser = BusinessUser::create([
        'business_id' => $this->business->id,
        'user_id' => $assignedUser->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $assignedBusinessUser->branches()->attach(
        $this->branch->id,
        [
            'is_active' => true,
        ]
    );

    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/tasks",
        [
            'title' => 'Personel görevi',
            'assigned_business_user_id' =>
                $assignedBusinessUser->id,
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.assigned_business_user.id',
            $assignedBusinessUser->id
        );
});

test('cannot create task assigning business user from another business', function () {
    $otherBusiness = Business::create([
        'name' => 'Other Klinik',
        'slug' => 'other-klinik-' . uniqid(),
    ]);

    $otherUser = User::factory()->create();

    $otherBusinessUser = BusinessUser::create([
        'business_id' => $otherBusiness->id,
        'user_id' => $otherUser->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/tasks",
        [
            'title' => 'Yetkisiz personel görevi',
            'assigned_business_user_id' =>
                $otherBusinessUser->id,
        ]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonPath(
            'success',
            false
        );
});

test('cannot create task assigning business user without active branch access', function () {
    $assignedUser = User::factory()->create();

    $assignedBusinessUser = BusinessUser::create([
        'business_id' => $this->business->id,
        'user_id' => $assignedUser->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $assignedBusinessUser->branches()->attach(
        $this->otherBranch->id,
        [
            'is_active' => true,
        ]
    );

    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/tasks",
        [
            'title' => 'Şube erişimi olmayan personel görevi',
            'assigned_business_user_id' =>
                $assignedBusinessUser->id,
        ]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonPath(
            'success',
            false
        );
});

test('cannot create task when authenticated business user has no active branch access', function () {
    $user = User::factory()->create();

    $businessUser = BusinessUser::create([
        'business_id' => $this->business->id,
        'user_id' => $user->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $businessUser->branches()->attach(
        $this->otherBranch->id,
        [
            'is_active' => true,
        ]
    );

    $this->actingAs($user);

    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/tasks",
        [
            'title' => 'Yetkisiz şube görevi',
        ]
    );

    $response
        ->assertForbidden()
        ->assertJsonPath(
            'success',
            false
        );
});

test('cannot create task using lead from another branch', function () {
    $lead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->otherBranch->id,
        'first_name' => 'Diğer',
        'last_name' => 'Lead',
    ]);

    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/tasks",
        [
            'crm_lead_id' => $lead->id,
            'title' => 'Yetkisiz görev',
        ]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonPath(
            'success',
            false
        );

    $this->assertDatabaseMissing('crm_tasks', [
        'crm_lead_id' => $lead->id,
        'title' => 'Yetkisiz görev',
    ]);
});

test('can show crm task', function () {
    $task = CrmTask::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'created_by_business_user_id' => $this->businessUser->id,
        'title' => 'Gösterilecek görev',
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/tasks/{$task->id}"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'data.id',
            $task->id
        )
        ->assertJsonPath(
            'data.title',
            'Gösterilecek görev'
        );
});

test('cannot show task belonging to another branch', function () {
    $task = CrmTask::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->otherBranch->id,
        'created_by_business_user_id' => $this->businessUser->id,
        'title' => 'Diğer şube görevi',
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/tasks/{$task->id}"
    );

    $response
        ->assertNotFound()
        ->assertJsonPath(
            'success',
            false
        );
});

test('can update crm task', function () {
    $task = CrmTask::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'created_by_business_user_id' => $this->businessUser->id,
        'title' => 'Eski görev',
        'status' => 'pending',
        'priority' => 'normal',
    ]);

    $response = $this->putJson(
        "/api/branches/{$this->branch->id}/crm/tasks/{$task->id}",
        [
            'title' => 'Yeni görev',
            'priority' => 'high',
        ]
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'data.title',
            'Yeni görev'
        )
        ->assertJsonPath(
            'data.priority',
            'high'
        );
});

test('cannot update crm task with lead from another branch', function () {
    $task = CrmTask::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'created_by_business_user_id' => $this->businessUser->id,
        'title' => 'Lead değiştirilecek görev',
        'status' => 'pending',
    ]);

    $lead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->otherBranch->id,
        'first_name' => 'Diğer',
        'last_name' => 'Lead',
    ]);

    $response = $this->putJson(
        "/api/branches/{$this->branch->id}/crm/tasks/{$task->id}",
        [
            'crm_lead_id' => $lead->id,
        ]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonPath(
            'success',
            false
        );

    expect($task->fresh()->crm_lead_id)
        ->toBeNull();
});

test('cannot update crm task with business user without active branch access', function () {
    $task = CrmTask::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'created_by_business_user_id' => $this->businessUser->id,
        'title' => 'Personel değiştirilecek görev',
        'status' => 'pending',
    ]);

    $assignedUser = User::factory()->create();

    $assignedBusinessUser = BusinessUser::create([
        'business_id' => $this->business->id,
        'user_id' => $assignedUser->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $assignedBusinessUser->branches()->attach(
        $this->otherBranch->id,
        [
            'is_active' => true,
        ]
    );

    $response = $this->putJson(
        "/api/branches/{$this->branch->id}/crm/tasks/{$task->id}",
        [
            'assigned_business_user_id' =>
                $assignedBusinessUser->id,
        ]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonPath(
            'success',
            false
        );

    expect($task->fresh()->assigned_business_user_id)
        ->toBeNull();
});

test('can move crm task to in progress and set started at', function () {
    $task = CrmTask::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'created_by_business_user_id' => $this->businessUser->id,
        'title' => 'Başlatılacak görev',
        'status' => 'pending',
    ]);

    $response = $this->putJson(
        "/api/branches/{$this->branch->id}/crm/tasks/{$task->id}",
        [
            'status' => 'in_progress',
        ]
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'data.status',
            'in_progress'
        );

    expect($task->fresh()->started_at)->not->toBeNull();
});

test('can complete crm task', function () {
    $task = CrmTask::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'created_by_business_user_id' => $this->businessUser->id,
        'title' => 'Tamamlanacak görev',
        'status' => 'pending',
    ]);

    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/tasks/{$task->id}/complete"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'success',
            true
        )
        ->assertJsonPath(
            'data.status',
            'completed'
        );

    expect($task->fresh()->completed_at)->not->toBeNull();
});

test('cannot complete already completed crm task', function () {
    $task = CrmTask::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'created_by_business_user_id' => $this->businessUser->id,
        'title' => 'Zaten tamamlandı',
        'status' => 'completed',
        'completed_at' => now(),
    ]);

    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/tasks/{$task->id}/complete"
    );

    $response
        ->assertUnprocessable()
        ->assertJsonPath(
            'success',
            false
        );
});

test('can cancel crm task', function () {
    $task = CrmTask::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'created_by_business_user_id' => $this->businessUser->id,
        'title' => 'İptal edilecek görev',
        'status' => 'pending',
    ]);

    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/tasks/{$task->id}/cancel"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'success',
            true
        )
        ->assertJsonPath(
            'data.status',
            'cancelled'
        );
});

test('cannot cancel completed crm task', function () {
    $task = CrmTask::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'created_by_business_user_id' => $this->businessUser->id,
        'title' => 'Tamamlanmış görev',
        'status' => 'completed',
        'completed_at' => now(),
    ]);

    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/tasks/{$task->id}/cancel"
    );

    $response
        ->assertUnprocessable()
        ->assertJsonPath(
            'success',
            false
        );
});

test('cannot cancel already cancelled crm task', function () {
    $task = CrmTask::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'created_by_business_user_id' => $this->businessUser->id,
        'title' => 'Zaten iptal edildi',
        'status' => 'cancelled',
    ]);

    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/tasks/{$task->id}/cancel"
    );

    $response
        ->assertUnprocessable()
        ->assertJsonPath(
            'success',
            false
        );
});