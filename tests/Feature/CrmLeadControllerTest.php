<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\CrmLead;
use App\Models\CrmPipelineStage;
use App\Models\CrmPipelineStageHistory;
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
});

test('can list crm leads for branch', function () {
    $lead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'first_name' => 'Ahmet',
        'last_name' => 'Yılmaz',
        'phone' => '5551112233',
        'source' => 'instagram',
        'status' => 'new',
        'priority' => 'high',
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/leads"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath(
            'data.0.id',
            $lead->id
        )
        ->assertJsonPath(
            'data.0.first_name',
            'Ahmet'
        )
        ->assertJsonPath(
            'data.0.source',
            'instagram'
        );
});

test('cannot list leads belonging to another branch', function () {
    CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->otherBranch->id,
        'first_name' => 'Mehmet',
        'last_name' => 'Kaya',
        'source' => 'facebook',
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/leads"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(0, 'data');
});

test('can filter leads by status', function () {
    $newLead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'first_name' => 'Yeni',
        'status' => 'new',
    ]);

    CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'first_name' => 'Teklif',
        'status' => 'offer',
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/leads?status=new"
    );

    $response
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath(
            'data.0.id',
            $newLead->id
        )
        ->assertJsonPath(
            'data.0.status',
            'new'
        );
});

test('can filter leads by source', function () {
    $instagramLead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'first_name' => 'Instagram',
        'source' => 'instagram',
    ]);

    CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'first_name' => 'Website',
        'source' => 'website',
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/leads?source=instagram"
    );

    $response
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath(
            'data.0.id',
            $instagramLead->id
        );
});

test('can filter leads by priority', function () {
    $urgentLead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'first_name' => 'Acil',
        'priority' => 'urgent',
    ]);

    CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'first_name' => 'Normal',
        'priority' => 'normal',
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/leads?priority=urgent"
    );

    $response
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath(
            'data.0.id',
            $urgentLead->id
        );
});

test('can create crm lead', function () {
    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/leads",
        [
            'first_name' => 'Ayşe',
            'last_name' => 'Demir',
            'email' => 'ayse@example.com',
            'phone' => '5551234567',
            'country_code' => '+90',
            'source' => 'instagram',
            'status' => 'new',
            'priority' => 'high',
            'notes' => 'Saç ekimi hakkında bilgi istedi.',
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
            'data.first_name',
            'Ayşe'
        )
        ->assertJsonPath(
            'data.last_name',
            'Demir'
        )
        ->assertJsonPath(
            'data.source',
            'instagram'
        )
        ->assertJsonPath(
            'data.status',
            'new'
        )
        ->assertJsonPath(
            'data.priority',
            'high'
        );

    $this->assertDatabaseHas(
        'crm_leads',
        [
            'business_id' => $this->business->id,
            'branch_id' => $this->branch->id,
            'first_name' => 'Ayşe',
            'last_name' => 'Demir',
            'source' => 'instagram',
        ]
    );
});

test('crm lead uses correct default values', function () {
    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/leads",
        [
            'first_name' => 'Ali',
            'last_name' => 'Veli',
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.source',
            'manual'
        )
        ->assertJsonPath(
            'data.status',
            'new'
        )
        ->assertJsonPath(
            'data.priority',
            'normal'
        );
});

test('can assign lead to business user', function () {
    $user = \App\Models\User::factory()->create();

    $businessUser = BusinessUser::create([
        'business_id' => $this->business->id,
        'user_id' => $user->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/leads",
        [
            'first_name' => 'Assigned',
            'assigned_business_user_id' => $businessUser->id,
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.assigned_business_user.id',
            $businessUser->id
        );
});

test('can show crm lead', function () {
    $lead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'first_name' => 'Show',
        'last_name' => 'Lead',
        'source' => 'website',
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/leads/{$lead->id}"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'data.id',
            $lead->id
        )
        ->assertJsonPath(
            'data.first_name',
            'Show'
        );
});

test('cannot show lead belonging to another branch', function () {
    $lead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->otherBranch->id,
        'first_name' => 'Other',
        'source' => 'website',
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/leads/{$lead->id}"
    );

    $response
        ->assertNotFound()
        ->assertJsonPath(
            'success',
            false
        );
});

test('can update crm lead', function () {
    $lead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'first_name' => 'Eski',
        'status' => 'new',
        'priority' => 'normal',
    ]);

    $response = $this->putJson(
        "/api/branches/{$this->branch->id}/crm/leads/{$lead->id}",
        [
            'first_name' => 'Yeni',
            'status' => 'contacted',
            'priority' => 'high',
            'notes' => 'İletişim sağlandı.',
        ]
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'data.first_name',
            'Yeni'
        )
        ->assertJsonPath(
            'data.status',
            'contacted'
        )
        ->assertJsonPath(
            'data.priority',
            'high'
        )
        ->assertJsonPath(
            'data.notes',
            'İletişim sağlandı.'
        );
});

test('cannot update lead belonging to another branch', function () {
    $lead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->otherBranch->id,
        'first_name' => 'Other',
    ]);

    $response = $this->putJson(
        "/api/branches/{$this->branch->id}/crm/leads/{$lead->id}",
        [
            'first_name' => 'Yetkisiz',
        ]
    );

    $response
        ->assertNotFound()
        ->assertJsonPath(
            'success',
            false
        );
});

test('can convert crm lead', function () {
    $lead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'first_name' => 'Dönüşen',
        'status' => 'appointment',
    ]);

    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/leads/{$lead->id}/convert"
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
        )
        ->assertJsonPath(
            'data.converted_at',
            fn ($value) => $value !== null
        );
});

test('cannot convert lead belonging to another branch', function () {
    $lead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->otherBranch->id,
        'first_name' => 'Other',
        'status' => 'appointment',
    ]);

    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/leads/{$lead->id}/convert"
    );

    $response
        ->assertNotFound()
        ->assertJsonPath(
            'success',
            false
        );
});

test('can mark crm lead as lost', function () {
    $lead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'first_name' => 'Kayıp',
        'status' => 'offer',
    ]);

    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/leads/{$lead->id}/lost",
        [
            'lost_reason' => 'Fiyat konusunda anlaşma sağlanamadı.',
        ]
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'success',
            true
        )
        ->assertJsonPath(
            'data.status',
            'lost'
        )
        ->assertJsonPath(
            'data.lost_reason',
            'Fiyat konusunda anlaşma sağlanamadı.'
        )
        ->assertJsonPath(
            'data.lost_at',
            fn ($value) => $value !== null
        );
});

test('lost reason is required', function () {
    $lead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'first_name' => 'Kayıp',
        'status' => 'offer',
    ]);

    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/leads/{$lead->id}/lost"
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'lost_reason',
        ]);
});

test('can filter leads by assigned business user', function () {
    $user = \App\Models\User::factory()->create();

    $businessUser = BusinessUser::create([
        'business_id' => $this->business->id,
        'user_id' => $user->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $assignedLead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'first_name' => 'Assigned',
        'assigned_business_user_id' => $businessUser->id,
    ]);

    CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'first_name' => 'Unassigned',
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/leads?assigned_business_user_id={$businessUser->id}"
    );

    $response
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath(
            'data.0.id',
            $assignedLead->id
        );
});

test('can create crm lead with pipeline stage history', function () {
    $stage = CrmPipelineStage::create([
        'business_id' => $this->business->id,
        'name' => 'Yeni Lead',
        'slug' => 'yeni-lead-' . uniqid(),
        'color' => '#3B82F6',
        'sort_order' => 1,
        'is_won' => false,
        'is_lost' => false,
        'is_active' => true,
    ]);

    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/leads",
        [
            'first_name' => 'History',
            'last_name' => 'Create',
            'pipeline_stage_id' => $stage->id,
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath('data.pipeline_stage_id', $stage->id);

    $this->assertDatabaseHas('crm_pipeline_stage_histories', [
        'crm_lead_id' => $response->json('data.id'),
        'from_stage_id' => null,
        'to_stage_id' => $stage->id,
        'notes' => 'Lead oluşturuldu.',
    ]);
});

test('can record pipeline stage change history', function () {
    $user = \App\Models\User::factory()->create();

    BusinessUser::create([
        'business_id' => $this->business->id,
        'user_id' => $user->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $this->actingAs($user);

    $fromStage = CrmPipelineStage::create([
        'business_id' => $this->business->id,
        'name' => 'Yeni',
        'slug' => 'yeni-' . uniqid(),
        'color' => '#3B82F6',
        'sort_order' => 1,
        'is_won' => false,
        'is_lost' => false,
        'is_active' => true,
    ]);

    $toStage = CrmPipelineStage::create([
        'business_id' => $this->business->id,
        'name' => 'İletişim Kuruldu',
        'slug' => 'iletisim-kuruldu-' . uniqid(),
        'color' => '#10B981',
        'sort_order' => 2,
        'is_won' => false,
        'is_lost' => false,
        'is_active' => true,
    ]);

    $lead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'first_name' => 'Stage',
        'last_name' => 'Change',
        'pipeline_stage_id' => $fromStage->id,
    ]);

    $response = $this->putJson(
        "/api/branches/{$this->branch->id}/crm/leads/{$lead->id}",
        [
            'pipeline_stage_id' => $toStage->id,
            'pipeline_stage_note' => 'Hasta ile ilk görüşme yapıldı.',
        ]
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('data.pipeline_stage_id', $toStage->id);

    $businessUser = BusinessUser::query()
        ->where('business_id', $this->business->id)
        ->where('user_id', $user->id)
        ->first();

    $this->assertDatabaseHas('crm_pipeline_stage_histories', [
        'crm_lead_id' => $lead->id,
        'from_stage_id' => $fromStage->id,
        'to_stage_id' => $toStage->id,
        'changed_by_business_user_id' => $businessUser->id,
        'notes' => 'Hasta ile ilk görüşme yapıldı.',
    ]);

    expect(
        CrmPipelineStageHistory::query()
            ->where('crm_lead_id', $lead->id)
            ->count()
    )->toBe(1);
});

test('does not create pipeline stage history when stage does not change', function () {
    $stage = CrmPipelineStage::create([
        'business_id' => $this->business->id,
        'name' => 'Yeni',
        'slug' => 'yeni-' . uniqid(),
        'color' => '#3B82F6',
        'sort_order' => 1,
        'is_won' => false,
        'is_lost' => false,
        'is_active' => true,
    ]);

    $lead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'first_name' => 'No',
        'last_name' => 'Stage Change',
        'pipeline_stage_id' => $stage->id,
    ]);

    $response = $this->putJson(
        "/api/branches/{$this->branch->id}/crm/leads/{$lead->id}",
        [
            'first_name' => 'Updated',
        ]
    );

    $response->assertSuccessful();

    $this->assertDatabaseMissing('crm_pipeline_stage_histories', [
        'crm_lead_id' => $lead->id,
    ]);
});

test('cannot move lead to pipeline stage belonging to another business', function () {
    $otherBusiness = Business::create([
        'name' => 'Other Klinik',
        'slug' => 'other-klinik-' . uniqid(),
    ]);

    $otherStage = CrmPipelineStage::create([
        'business_id' => $otherBusiness->id,
        'name' => 'Başka İşletme Aşaması',
        'slug' => 'baska-isletme-asamasi-' . uniqid(),
        'color' => '#EF4444',
        'sort_order' => 1,
        'is_won' => false,
        'is_lost' => false,
        'is_active' => true,
    ]);

    $lead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'first_name' => 'Isolation',
        'last_name' => 'Test',
    ]);

    $response = $this->putJson(
        "/api/branches/{$this->branch->id}/crm/leads/{$lead->id}",
        [
            'pipeline_stage_id' => $otherStage->id,
        ]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonPath('success', false);

    expect($lead->fresh()->pipeline_stage_id)->toBeNull();

    $this->assertDatabaseMissing('crm_pipeline_stage_histories', [
        'crm_lead_id' => $lead->id,
        'to_stage_id' => $otherStage->id,
    ]);
});