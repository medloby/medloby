<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Conversation;
use App\Models\CrmActivity;
use App\Models\CrmFollowUp;
use App\Models\CrmLead;
use App\Models\CrmPipelineStage;
use App\Models\CrmPipelineStageHistory;
use App\Models\CrmTask;
use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->business = Business::factory()->create([
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

    $owner = User::factory()->create();
    $this->business->memberships()->create([
        'user_id' => $owner->id,
        'role' => 'business_owner',
        'is_active' => true,
    ]);
    $this->actingAs($owner);
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

test('paginated crm leads are ordered, filtered and limited to fields needed for the branch list', function () {
    $this->freezeTime();
    $branchLead = [
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'status' => 'new',
    ];
    $first = CrmLead::create($branchLead + ['first_name' => 'Önceki kayıt']);
    $stage = CrmPipelineStage::create([
        'business_id' => $this->business->id,
        'name' => 'İlk temas',
        'slug' => 'ilk-temas',
    ]);
    $latest = CrmLead::create($branchLead + [
        'first_name' => 'Son kayıt',
        'pipeline_stage_id' => $stage->id,
        'notes' => 'Sadece ayrıntı ekranı için not.',
    ]);
    CrmLead::create(array_replace($branchLead, ['branch_id' => $this->otherBranch->id]));
    CrmLead::create(array_replace($branchLead, ['status' => 'contacted']));

    $path = "/api/branches/{$this->branch->id}/crm/leads?status=new&per_page=1";
    $this->getJson($path)
        ->assertOk()
        ->assertJsonPath('data.total', 2)
        ->assertJsonPath('data.last_page', 2)
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.id', $latest->id)
        ->assertJsonPath('data.data.0.pipeline_stage.name', 'İlk temas')
        ->assertJsonMissingPath('data.data.0.notes')
        ->assertJsonMissingPath('data.data.0.patient_profile');

    $this->getJson($path.'&page=2')->assertOk()->assertJsonPath('data.data.0.id', $first->id);
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
    $businessUser->branches()->attach($this->branch->id, ['is_active' => true]);

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

test('returns only names and membership IDs that can be assigned in the selected branch', function () {
    $owner = $this->business->memberships()->sole();
    $owner->user->update(['name' => 'Klinik Sahibi']);

    $createMembership = fn (Business $business, string $name, bool $active = true) => $business->memberships()->create([
        'user_id' => User::factory()->create(['name' => $name])->id,
        'role' => 'staff',
        'is_active' => $active,
    ]);
    $staff = $createMembership($this->business, 'Asistan Merkez');
    $staff->branches()->attach($this->branch->id, ['is_active' => true]);
    $createMembership($this->business, 'Diğer Şube')->branches()
        ->attach($this->otherBranch->id, ['is_active' => true]);
    $createMembership($this->business, 'Pasif Şube Yetkisi')->branches()
        ->attach($this->branch->id, ['is_active' => false]);
    $createMembership($this->business, 'Pasif Personel', false)->branches()
        ->attach($this->branch->id, ['is_active' => true]);
    $createMembership(Business::factory()->create(), 'Farklı Klinik');

    $this->getJson("/api/branches/{$this->branch->id}/crm/leads/assignees")
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson([
            'success' => true,
            'data' => [
                ['id' => $staff->id, 'name' => 'Asistan Merkez'],
                ['id' => $owner->id, 'name' => 'Klinik Sahibi'],
            ],
        ]);

    $this->actingAs($staff->user)->getJson("/api/branches/{$this->otherBranch->id}/crm/leads/assignees")
        ->assertForbidden();
});

test('crm links patients and staff only within the lead branch', function () {
    $patient = PatientProfile::create([
        'user_id' => User::factory()->create()->id,
        'first_name' => 'Branch', 'last_name' => 'Patient',
        'phone' => '05550000000', 'birth_date' => '1992-01-01',
    ]);
    Conversation::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->otherBranch->id,
        'patient_profile_id' => $patient->id,
        'status' => 'open',
    ]);
    $staff = $this->business->memberships()->create([
        'user_id' => User::factory()->create()->id, 'role' => 'staff', 'is_active' => true,
    ]);
    $staff->branches()->attach($this->otherBranch->id, ['is_active' => true]);
    $links = ['patient_profile_id' => $patient->id, 'assigned_business_user_id' => $staff->id];
    $url = "/api/branches/{$this->branch->id}/crm/leads";

    $this->postJson($url, $links)->assertUnprocessable()->assertJsonValidationErrors(array_keys($links));
    $this->assertDatabaseCount('crm_leads', 0);

    $lead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        ...$links,
    ]);
    $leadUrl = $url.'/'.$lead->id;
    $this->getJson($leadUrl)
        ->assertOk()
        ->assertJsonPath('data.patient_profile', null)
        ->assertJsonPath('data.patient_profile_id', null)
        ->assertJsonPath('data.assigned_business_user', null)
        ->assertJsonPath('data.assigned_business_user_id', null);
    $this->assertDatabaseHas('crm_leads', ['id' => $lead->id, ...$links]);

    $this->putJson($leadUrl, $links)->assertUnprocessable()->assertJsonValidationErrors(array_keys($links));
    $appointment = Appointment::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'patient_profile_id' => $patient->id,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->subDay()->addHour(),
        'status' => 'completed',
    ]);
    $staff->branches()->attach($this->branch->id, ['is_active' => true]);
    $this->putJson($leadUrl, $links)->assertUnprocessable()->assertJsonValidationErrors(['patient_profile_id']);
    $appointment->statusHistories()->create([
        'new_status' => 'pending',
        'changed_by_user_id' => $patient->user_id,
        'changed_at' => $appointment->created_at,
    ]);
    $this->putJson($leadUrl, $links)->assertOk()
        ->assertJsonPath('data.patient_profile_id', $patient->id)
        ->assertJsonPath('data.patient_profile', ['id' => $patient->id, 'first_name' => 'Branch', 'last_name' => 'Patient'])
        ->assertJsonPath('data.assigned_business_user.id', $staff->id);
    $conversation = Conversation::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'patient_profile_id' => $patient->id,
        'status' => 'open',
    ]);
    $this->postJson($leadUrl."/conversations/{$conversation->id}/attach", [])->assertOk();

    $this->putJson($leadUrl, ['patient_profile_id' => null])
        ->assertUnprocessable()->assertJsonValidationErrors(['patient_profile_id']);
    $this->assertDatabaseHas('crm_leads', ['id' => $lead->id, ...$links]);
    $this->assertDatabaseHas('conversations', ['id' => $conversation->id, 'crm_lead_id' => $lead->id]);
});

test('CRM work only exposes candidate details within its own branch', function (string $collection, string $model) {
    $staff = User::factory()->create();
    $member = $this->business->memberships()->create(['user_id' => $staff->id, 'role' => 'staff', 'is_active' => true]);
    $member->branches()->attach($this->branch->id, ['is_active' => true]);
    $localLead = CrmLead::create([
        'business_id' => $this->business->id, 'branch_id' => $this->branch->id,
        'first_name' => 'Şube', 'last_name' => 'Adayı',
    ]);
    $foreignLead = CrmLead::create([
        'business_id' => $this->business->id, 'branch_id' => $this->otherBranch->id,
        'first_name' => 'Diğer şube', 'email' => 'other-branch@example.test',
    ]);
    $attributes = match ($collection) {
        'activities' => ['business_user_id' => $member->id, 'occurred_at' => now()],
        'follow-ups' => ['scheduled_at' => now()],
        default => [],
    };
    $work = [
        ...$attributes, 'business_id' => $this->business->id, 'branch_id' => $this->branch->id,
        'type' => 'call', 'title' => 'Şube kaydı',
    ];
    $ordinary = $model::create([...$work, 'crm_lead_id' => $localLead->id]);
    $legacy = $model::create([...$work, 'crm_lead_id' => $foreignLead->id]);
    $path = "/api/branches/{$this->branch->id}/crm/{$collection}";

    $this->actingAs($staff)->getJson("{$path}/{$legacy->id}")->assertOk()
        ->assertJsonPath('data.crm_lead_id', null)->assertJsonPath('data.lead', null)
        ->assertJsonMissing(['email' => 'other-branch@example.test']);
    $this->getJson("{$path}/{$ordinary->id}")->assertOk()
        ->assertJsonPath('data.crm_lead_id', $localLead->id)
        ->assertJsonPath('data.lead', ['id' => $localLead->id, 'first_name' => 'Şube', 'last_name' => 'Adayı']);
    $list = $this->getJson($path)->assertOk()->assertJsonMissing(['email' => 'other-branch@example.test']);
    $legacySummary = collect($list->json('data'))->firstWhere('id', $legacy->id);
    expect($legacySummary['crm_lead_id'])->toBeNull();
    expect($legacySummary['lead'])->toBeNull();
    $this->getJson($path.'?crm_lead_id='.$foreignLead->id)->assertOk()->assertJsonCount(0, 'data');
    $this->getJson($path.'?crm_lead_id='.$localLead->id)->assertOk()->assertJsonPath('data.0.id', $ordinary->id);
    $this->putJson("{$path}/{$legacy->id}", ['title' => 'Düzenlenen şube kaydı'])->assertOk()
        ->assertJsonPath('data.crm_lead_id', null)->assertJsonPath('data.lead', null);
    expect($legacy->refresh()->crm_lead_id)->toBe($foreignLead->id);
})->with([
    'task' => ['tasks', CrmTask::class],
    'follow-up' => ['follow-ups', CrmFollowUp::class],
    'activity' => ['activities', CrmActivity::class],
]);

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
            'converted'
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

test('lead outcomes agree between the detail, status filter and dashboard for legacy and new records', function () {
    $attributes = ['business_id' => $this->business->id, 'branch_id' => $this->branch->id];
    $legacy = CrmLead::create([...$attributes, 'status' => 'completed', 'converted_at' => now()->subDay()]);
    $open = CrmLead::create([...$attributes, 'status' => 'offer']);
    $crm = "/api/branches/{$this->branch->id}/crm";
    $legacyUrl = "{$crm}/leads/{$legacy->id}";
    $url = "{$crm}/leads/{$open->id}";

    $this->getJson($legacyUrl)->assertOk()->assertJsonPath('data.status', 'converted');
    $this->putJson($url, ['status' => 'lost'])->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->postJson($url.'/convert')->assertOk()->assertJsonPath('data.status', 'converted');
    $this->getJson($crm.'/dashboard')->assertOk()->assertJsonPath('data.summary.converted_leads', 2);
    $this->getJson($crm.'/leads?status=converted&per_page=12')
        ->assertOk()->assertJsonPath('data.total', 2)
        ->assertJsonPath('data.data.0.status', 'converted')
        ->assertJsonPath('data.data.1.status', 'converted');
    $this->assertDatabaseHas('crm_leads', ['id' => $legacy->id, 'status' => 'completed']);
    $this->assertDatabaseHas('crm_leads', ['id' => $open->id, 'status' => 'converted']);
});

test('retrying finalization retains the original result and does not allow a different outcome', function () {
    $lead = CrmLead::create(['business_id' => $this->business->id, 'branch_id' => $this->branch->id]);
    $stage = CrmPipelineStage::create(['business_id' => $this->business->id, 'name' => 'Takip', 'slug' => 'takip']);
    $url = "/api/branches/{$this->branch->id}/crm/leads/{$lead->id}";

    $first = $this->postJson($url.'/lost', ['lost_reason' => 'Talep geri çekildi.'])->assertOk();
    $this->travel(2)->minutes();
    $this->postJson($url.'/lost', ['lost_reason' => 'Daha sonra tekrar gönderildi.'])
        ->assertOk()
        ->assertJsonPath('data.lost_at', $first->json('data.lost_at'))
        ->assertJsonPath('data.lost_reason', 'Talep geri çekildi.');
    $this->postJson($url.'/convert')->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->putJson($url, ['status' => 'new'])->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->putJson($url, ['lost_reason' => 'Değişiklik'])->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->putJson($url, ['pipeline_stage_id' => $stage->id])
        ->assertUnprocessable()->assertJsonValidationErrors('pipeline_stage_id');
    $this->putJson($url, ['phone' => '05550000000'])
        ->assertOk()->assertJsonPath('data.phone', '05550000000')->assertJsonPath('data.status', 'lost');
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
    $businessUser->branches()->attach($this->branch->id, ['is_active' => true]);

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

    $membership = BusinessUser::create([
        'business_id' => $this->business->id,
        'user_id' => $user->id,
        'role' => 'staff',
        'is_active' => true,
    ]);
    $membership->branches()->attach($this->branch->id, ['is_active' => true]);

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

    $this->getJson("/api/branches/{$this->branch->id}/crm/leads/{$lead->id}")
        ->assertOk()
        ->assertJsonPath('data.pipeline_stage_histories.0.from_stage.name', $fromStage->name)
        ->assertJsonPath('data.pipeline_stage_histories.0.to_stage.name', $toStage->name)
        ->assertJsonPath('data.pipeline_stage_histories.0.changed_by_business_user.user.name', $user->name)
        ->assertJsonPath('data.pipeline_stage_histories.0.notes', 'Hasta ile ilk görüşme yapıldı.');

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
