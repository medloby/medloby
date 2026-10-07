<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\CrmLead;
use App\Models\CrmPipelineStage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->business = Business::create([
        'name' => 'Test Klinik',
        'slug' => 'test-klinik-' . uniqid(),
    ]);

    $this->otherBusiness = Business::create([
        'name' => 'Diğer Klinik',
        'slug' => 'diger-klinik-' . uniqid(),
    ]);

    $this->branch = Branch::create([
        'business_id' => $this->business->id,
        'name' => 'Merkez Şube',
        'slug' => 'merkez-sube-' . uniqid(),
        'status' => 'active',
    ]);

    $this->otherBranch = Branch::create([
        'business_id' => $this->otherBusiness->id,
        'name' => 'Diğer Merkez',
        'slug' => 'diger-merkez-' . uniqid(),
        'status' => 'active',
    ]);
});

test('can list active pipeline stages for business', function () {
    $firstStage = CrmPipelineStage::create([
        'business_id' => $this->business->id,
        'name' => 'Yeni Lead',
        'slug' => 'yeni-lead',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $secondStage = CrmPipelineStage::create([
        'business_id' => $this->business->id,
        'name' => 'Teklif',
        'slug' => 'teklif',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    CrmPipelineStage::create([
        'business_id' => $this->business->id,
        'name' => 'Pasif Aşama',
        'slug' => 'pasif-asama',
        'sort_order' => 3,
        'is_active' => false,
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/pipeline-stages"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath(
            'data.0.id',
            $firstStage->id
        )
        ->assertJsonPath(
            'data.1.id',
            $secondStage->id
        );
});

test('pipeline stages are returned in sort order', function () {
    $lateStage = CrmPipelineStage::create([
        'business_id' => $this->business->id,
        'name' => 'Teklif',
        'slug' => 'teklif',
        'sort_order' => 3,
    ]);

    $firstStage = CrmPipelineStage::create([
        'business_id' => $this->business->id,
        'name' => 'Yeni Lead',
        'slug' => 'yeni-lead',
        'sort_order' => 1,
    ]);

    $middleStage = CrmPipelineStage::create([
        'business_id' => $this->business->id,
        'name' => 'Ön Görüşme',
        'slug' => 'on-gorusme',
        'sort_order' => 2,
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/pipeline-stages"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'data.0.id',
            $firstStage->id
        )
        ->assertJsonPath(
            'data.1.id',
            $middleStage->id
        )
        ->assertJsonPath(
            'data.2.id',
            $lateStage->id
        );
});

test('cannot list pipeline stages belonging to another business', function () {
    CrmPipelineStage::create([
        'business_id' => $this->otherBusiness->id,
        'name' => 'Diğer Pipeline',
        'slug' => 'diger-pipeline',
        'sort_order' => 1,
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/pipeline-stages"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(0, 'data');
});

test('can create pipeline stage', function () {
    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/pipeline-stages",
        [
            'name' => 'Yeni Lead',
            'color' => '#2563EB',
            'sort_order' => 1,
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
            'data.name',
            'Yeni Lead'
        )
        ->assertJsonPath(
            'data.slug',
            'yeni-lead'
        )
        ->assertJsonPath(
            'data.color',
            '#2563EB'
        )
        ->assertJsonPath(
            'data.sort_order',
            1
        )
        ->assertJsonPath(
            'data.is_won',
            false
        )
        ->assertJsonPath(
            'data.is_lost',
            false
        )
        ->assertJsonPath(
            'data.is_active',
            true
        );

    $this->assertDatabaseHas(
        'crm_pipeline_stages',
        [
            'business_id' => $this->business->id,
            'name' => 'Yeni Lead',
            'slug' => 'yeni-lead',
        ]
    );
});

test('pipeline stage slug is generated automatically', function () {
    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/pipeline-stages",
        [
            'name' => 'Ön Görüşme',
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.slug',
            'on-gorusme'
        );
});

test('duplicate pipeline stage slug gets a unique slug', function () {
    CrmPipelineStage::create([
        'business_id' => $this->business->id,
        'name' => 'Yeni Lead',
        'slug' => 'yeni-lead',
    ]);

    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/pipeline-stages",
        [
            'name' => 'Yeni Lead',
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.slug',
            'yeni-lead-1'
        );
});

test('same slug can exist for different businesses', function () {
    CrmPipelineStage::create([
        'business_id' => $this->business->id,
        'name' => 'Yeni Lead',
        'slug' => 'yeni-lead',
    ]);

    $response = $this->postJson(
        "/api/branches/{$this->otherBranch->id}/crm/pipeline-stages",
        [
            'name' => 'Yeni Lead',
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.slug',
            'yeni-lead'
        )
        ->assertJsonPath(
            'data.business_id',
            $this->otherBusiness->id
        );
});

test('can create won pipeline stage', function () {
    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/pipeline-stages",
        [
            'name' => 'Tamamlandı',
            'is_won' => true,
            'sort_order' => 7,
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.is_won',
            true
        )
        ->assertJsonPath(
            'data.is_lost',
            false
        );
});

test('can create lost pipeline stage', function () {
    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/pipeline-stages",
        [
            'name' => 'Kaybedildi',
            'is_lost' => true,
            'sort_order' => 8,
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.is_lost',
            true
        )
        ->assertJsonPath(
            'data.is_won',
            false
        );
});

test('can show pipeline stage', function () {
    $stage = CrmPipelineStage::create([
        'business_id' => $this->business->id,
        'name' => 'Teklif',
        'slug' => 'teklif',
        'sort_order' => 4,
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/pipeline-stages/{$stage->id}"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'data.id',
            $stage->id
        )
        ->assertJsonPath(
            'data.name',
            'Teklif'
        )
        ->assertJsonPath(
            'data.business.id',
            $this->business->id
        );
});

test('cannot show pipeline stage belonging to another business', function () {
    $stage = CrmPipelineStage::create([
        'business_id' => $this->otherBusiness->id,
        'name' => 'Diğer',
        'slug' => 'diger',
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/pipeline-stages/{$stage->id}"
    );

    $response
        ->assertNotFound()
        ->assertJsonPath(
            'success',
            false
        );
});

test('can update pipeline stage', function () {
    $stage = CrmPipelineStage::create([
        'business_id' => $this->business->id,
        'name' => 'Eski Aşama',
        'slug' => 'eski-asama',
        'sort_order' => 1,
    ]);

    $response = $this->putJson(
        "/api/branches/{$this->branch->id}/crm/pipeline-stages/{$stage->id}",
        [
            'name' => 'İletişime Geçildi',
            'slug' => 'iletisime-gecildi',
            'color' => '#10B981',
            'sort_order' => 2,
        ]
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'data.name',
            'İletişime Geçildi'
        )
        ->assertJsonPath(
            'data.slug',
            'iletisime-gecildi'
        )
        ->assertJsonPath(
            'data.color',
            '#10B981'
        )
        ->assertJsonPath(
            'data.sort_order',
            2
        );
});

test('cannot update pipeline stage to duplicate slug', function () {
    $firstStage = CrmPipelineStage::create([
        'business_id' => $this->business->id,
        'name' => 'Yeni Lead',
        'slug' => 'yeni-lead',
    ]);

    $secondStage = CrmPipelineStage::create([
        'business_id' => $this->business->id,
        'name' => 'Teklif',
        'slug' => 'teklif',
    ]);

    $response = $this->putJson(
        "/api/branches/{$this->branch->id}/crm/pipeline-stages/{$secondStage->id}",
        [
            'slug' => $firstStage->slug,
        ]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonPath(
            'success',
            false
        );
});

test('cannot update pipeline stage belonging to another business', function () {
    $stage = CrmPipelineStage::create([
        'business_id' => $this->otherBusiness->id,
        'name' => 'Diğer',
        'slug' => 'diger',
    ]);

    $response = $this->putJson(
        "/api/branches/{$this->branch->id}/crm/pipeline-stages/{$stage->id}",
        [
            'name' => 'Yetkisiz Güncelleme',
        ]
    );

    $response
        ->assertNotFound()
        ->assertJsonPath(
            'success',
            false
        );
});

test('can deactivate pipeline stage', function () {
    $stage = CrmPipelineStage::create([
        'business_id' => $this->business->id,
        'name' => 'Eski Aşama',
        'slug' => 'eski-asama',
        'is_active' => true,
    ]);

    $response = $this->deleteJson(
        "/api/branches/{$this->branch->id}/crm/pipeline-stages/{$stage->id}"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'success',
            true
        )
        ->assertJsonPath(
            'data.is_active',
            false
        );

    $this->assertDatabaseHas(
        'crm_pipeline_stages',
        [
            'id' => $stage->id,
            'is_active' => false,
        ]
    );
});

test('deactivated pipeline stage is not returned in active list', function () {
    $stage = CrmPipelineStage::create([
        'business_id' => $this->business->id,
        'name' => 'Pasif Aşama',
        'slug' => 'pasif-asama',
        'is_active' => true,
    ]);

    $this->deleteJson(
        "/api/branches/{$this->branch->id}/crm/pipeline-stages/{$stage->id}"
    );

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/pipeline-stages"
    );

    $response
        ->assertSuccessful()
        ->assertJsonCount(0, 'data');
});

test('cannot deactivate pipeline stage belonging to another business', function () {
    $stage = CrmPipelineStage::create([
        'business_id' => $this->otherBusiness->id,
        'name' => 'Diğer',
        'slug' => 'diger',
        'is_active' => true,
    ]);

    $response = $this->deleteJson(
        "/api/branches/{$this->branch->id}/crm/pipeline-stages/{$stage->id}"
    );

    $response
        ->assertNotFound()
        ->assertJsonPath(
            'success',
            false
        );

    $this->assertDatabaseHas(
        'crm_pipeline_stages',
        [
            'id' => $stage->id,
            'is_active' => true,
        ]
    );
});

test('pipeline stage can have leads', function () {
    $stage = CrmPipelineStage::create([
        'business_id' => $this->business->id,
        'name' => 'Teklif',
        'slug' => 'teklif',
        'sort_order' => 4,
    ]);

    $lead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'pipeline_stage_id' => $stage->id,
        'first_name' => 'Test',
        'last_name' => 'Lead',
    ]);

    expect($stage->leads()->first()->id)
        ->toBe($lead->id);
});