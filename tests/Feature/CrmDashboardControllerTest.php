<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\CrmActivity;
use App\Models\CrmFollowUp;
use App\Models\CrmLead;
use App\Models\CrmPipelineStage;
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

test('can view crm dashboard for branch', function () {
    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/dashboard"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'data' => [
                'summary',
                'pipeline',
                'lead_sources',
                'staff_performance',
            ],
        ]);
});

test('crm dashboard returns correct lead summary', function () {
    CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'first_name' => 'Yeni',
        'last_name' => 'Lead',
        'status' => 'new',
    ]);

    CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'first_name' => 'Converted',
        'last_name' => 'Lead',
        'status' => 'converted',
    ]);

    CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'first_name' => 'Lost',
        'last_name' => 'Lead',
        'status' => 'lost',
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/dashboard"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('data.summary.total_leads', 3)
        ->assertJsonPath('data.summary.new_leads', 1)
        ->assertJsonPath('data.summary.converted_leads', 1)
        ->assertJsonPath('data.summary.lost_leads', 1);
});

test('crm dashboard returns pipeline lead counts', function () {
    $stageNew = CrmPipelineStage::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'name' => 'Yeni',
        'slug' => 'yeni-' . uniqid(),
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $stageContacted = CrmPipelineStage::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'name' => 'İletişime Geçildi',
        'slug' => 'iletisime-gecildi-' . uniqid(),
        'sort_order' => 2,
        'is_active' => true,
    ]);

    CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'pipeline_stage_id' => $stageNew->id,
        'first_name' => 'Ahmet',
        'last_name' => 'Yılmaz',
        'status' => 'new',
    ]);

    CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'pipeline_stage_id' => $stageNew->id,
        'first_name' => 'Mehmet',
        'last_name' => 'Kaya',
        'status' => 'new',
    ]);

    CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'pipeline_stage_id' => $stageContacted->id,
        'first_name' => 'Ayşe',
        'last_name' => 'Demir',
        'status' => 'new',
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/dashboard"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'data.pipeline.0.id',
            $stageNew->id
        )
        ->assertJsonPath(
            'data.pipeline.0.lead_count',
            2
        )
        ->assertJsonPath(
            'data.pipeline.1.id',
            $stageContacted->id
        )
        ->assertJsonPath(
            'data.pipeline.1.lead_count',
            1
        );
});

test('crm dashboard returns lead sources', function () {
    CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'first_name' => 'Lead',
        'last_name' => 'One',
        'source' => 'website',
        'status' => 'new',
    ]);

    CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'first_name' => 'Lead',
        'last_name' => 'Two',
        'source' => 'website',
        'status' => 'new',
    ]);

    CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'first_name' => 'Lead',
        'last_name' => 'Three',
        'source' => 'instagram',
        'status' => 'new',
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/dashboard"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'data.lead_sources.0.source',
            'website'
        )
        ->assertJsonPath(
            'data.lead_sources.0.lead_count',
            2
        )
        ->assertJsonPath(
            'data.lead_sources.1.source',
            'instagram'
        )
        ->assertJsonPath(
            'data.lead_sources.1.lead_count',
            1
        );
});

test('crm dashboard returns follow up summary', function () {
    $lead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'first_name' => 'Takip',
        'last_name' => 'Lead',
        'status' => 'new',
    ]);

    CrmFollowUp::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'crm_lead_id' => $lead->id,
        'assigned_business_user_id' => $this->businessUser->id,
        'type' => 'call',
        'title' => 'Bugünkü takip',
        'scheduled_at' => now(),
        'status' => 'pending',
    ]);

    CrmFollowUp::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'crm_lead_id' => $lead->id,
        'assigned_business_user_id' => $this->businessUser->id,
        'type' => 'email',
        'title' => 'Gelecek takip',
        'scheduled_at' => now()->addDay(),
        'status' => 'pending',
    ]);

    CrmFollowUp::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'crm_lead_id' => $lead->id,
        'assigned_business_user_id' => $this->businessUser->id,
        'type' => 'call',
        'title' => 'Tamamlanan takip',
        'scheduled_at' => now()->subDay(),
        'completed_at' => now(),
        'status' => 'completed',
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/dashboard"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'data.summary.pending_follow_ups',
            2
        )
        ->assertJsonPath(
            'data.summary.today_follow_ups',
            1
        )
        ->assertJsonPath(
            'data.summary.completed_follow_ups',
            1
        );
});

test('crm dashboard returns task summary', function () {
    $lead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'first_name' => 'Task',
        'last_name' => 'Lead',
        'status' => 'new',
    ]);

    CrmTask::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'crm_lead_id' => $lead->id,
        'business_user_id' => $this->businessUser->id,
        'title' => 'Açık görev',
        'status' => 'pending',
    ]);

    CrmTask::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'crm_lead_id' => $lead->id,
        'business_user_id' => $this->businessUser->id,
        'title' => 'Tamamlanan görev',
        'status' => 'completed',
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/dashboard"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'data.summary.open_tasks',
            1
        )
        ->assertJsonPath(
            'data.summary.completed_tasks',
            1
        );
});

test('crm dashboard returns todays activity count', function () {
    $lead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'first_name' => 'Activity',
        'last_name' => 'Lead',
        'status' => 'new',
    ]);

    CrmActivity::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'crm_lead_id' => $lead->id,
        'business_user_id' => $this->businessUser->id,
        'type' => 'call',
        'title' => 'Bugünkü görüşme',
        'description' => 'Bugünkü görüşme',
        'occurred_at' => now(),
    ]);

    CrmActivity::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'crm_lead_id' => $lead->id,
        'business_user_id' => $this->businessUser->id,
        'type' => 'email',
        'title' => 'Dünkü görüşme',
        'description' => 'Dünkü görüşme',
        'occurred_at' => now()->subDay(),
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/dashboard"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'data.summary.today_activities',
            1
        );
});

test('crm dashboard returns staff performance', function () {
    $lead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'assigned_business_user_id' => $this->businessUser->id,
        'first_name' => 'Personel',
        'last_name' => 'Lead',
        'status' => 'new',
    ]);

    CrmTask::create([
    'business_id' => $this->business->id,
    'branch_id' => $this->branch->id,
    'crm_lead_id' => $lead->id,
    'assigned_business_user_id' => $this->businessUser->id,
    'title' => 'Personel görevi',
    'status' => 'completed',
]);

    CrmFollowUp::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'crm_lead_id' => $lead->id,
        'assigned_business_user_id' => $this->businessUser->id,
        'type' => 'call',
        'title' => 'Personel follow-up',
        'scheduled_at' => now()->addDay(),
        'status' => 'pending',
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/dashboard"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'data.staff_performance.0.business_user_id',
            $this->businessUser->id
        )
        ->assertJsonPath(
            'data.staff_performance.0.lead_count',
            1
        )
        ->assertJsonPath(
            'data.staff_performance.0.task_count',
            1
        )
        ->assertJsonPath(
            'data.staff_performance.0.completed_task_count',
            1
        )
        ->assertJsonPath(
            'data.staff_performance.0.pending_follow_up_count',
            1
        );
});

test('crm dashboard excludes data belonging to another branch', function () {
    $otherUser = User::factory()->create();

    $otherBusinessUser = BusinessUser::create([
        'business_id' => $this->business->id,
        'user_id' => $otherUser->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $otherBusinessUser->branches()->attach(
        $this->otherBranch->id,
        [
            'is_active' => true,
        ]
    );

    $otherLead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->otherBranch->id,
        'assigned_business_user_id' => $otherBusinessUser->id,
        'first_name' => 'Diğer',
        'last_name' => 'Şube',
        'status' => 'new',
    ]);

    CrmFollowUp::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->otherBranch->id,
        'crm_lead_id' => $otherLead->id,
        'assigned_business_user_id' => $otherBusinessUser->id,
        'type' => 'call',
        'title' => 'Diğer şube takip',
        'scheduled_at' => now(),
        'status' => 'pending',
    ]);

    CrmTask::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->otherBranch->id,
        'crm_lead_id' => $otherLead->id,
        'business_user_id' => $otherBusinessUser->id,
        'title' => 'Diğer şube görev',
        'status' => 'pending',
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/dashboard"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'data.summary.total_leads',
            0
        )
        ->assertJsonPath(
            'data.summary.pending_follow_ups',
            0
        )
        ->assertJsonPath(
            'data.summary.open_tasks',
            0
        );
});