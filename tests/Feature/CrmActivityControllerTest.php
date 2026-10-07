<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\CrmActivity;
use App\Models\CrmLead;
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

    $this->lead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'first_name' => 'Ahmet',
        'last_name' => 'Yılmaz',
        'status' => 'new',
    ]);

    $this->actingAs($this->user);
});

test('can list crm activities for branch', function () {
    $activity = CrmActivity::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'crm_lead_id' => $this->lead->id,
        'business_user_id' => $this->businessUser->id,
        'type' => 'call',
        'title' => 'Hasta ile telefon görüşmesi',
        'description' => 'Hasta saç ekimi fiyatlarını sordu.',
        'occurred_at' => now(),
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/activities"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath(
            'data.0.id',
            $activity->id
        )
        ->assertJsonPath(
            'data.0.title',
            'Hasta ile telefon görüşmesi'
        )
        ->assertJsonPath(
            'data.0.type',
            'call'
        );
});

test('cannot list activities belonging to another branch', function () {
    CrmActivity::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->otherBranch->id,
        'crm_lead_id' => CrmLead::create([
            'business_id' => $this->business->id,
            'branch_id' => $this->otherBranch->id,
            'first_name' => 'Diğer',
            'last_name' => 'Lead',
            'status' => 'new',
        ])->id,
        'business_user_id' => $this->businessUser->id,
        'type' => 'note',
        'title' => 'Diğer şube aktivitesi',
        'occurred_at' => now(),
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/activities"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(0, 'data');
});

test('can filter activities by lead', function () {
    $otherLead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'first_name' => 'Mehmet',
        'last_name' => 'Kaya',
        'status' => 'new',
    ]);

    $leadActivity = CrmActivity::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'crm_lead_id' => $this->lead->id,
        'business_user_id' => $this->businessUser->id,
        'type' => 'call',
        'title' => 'Ahmet görüşmesi',
        'occurred_at' => now(),
    ]);

    CrmActivity::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'crm_lead_id' => $otherLead->id,
        'business_user_id' => $this->businessUser->id,
        'type' => 'note',
        'title' => 'Mehmet notu',
        'occurred_at' => now(),
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/activities?crm_lead_id={$this->lead->id}"
    );

    $response
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath(
            'data.0.id',
            $leadActivity->id
        );
});

test('can filter activities by type', function () {
    $callActivity = CrmActivity::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'crm_lead_id' => $this->lead->id,
        'business_user_id' => $this->businessUser->id,
        'type' => 'call',
        'title' => 'Telefon görüşmesi',
        'occurred_at' => now(),
    ]);

    CrmActivity::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'crm_lead_id' => $this->lead->id,
        'business_user_id' => $this->businessUser->id,
        'type' => 'note',
        'title' => 'Not',
        'occurred_at' => now(),
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/activities?type=call"
    );

    $response
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath(
            'data.0.id',
            $callActivity->id
        )
        ->assertJsonPath(
            'data.0.type',
            'call'
        );
});

test('can create crm activity', function () {
    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/activities",
        [
            'crm_lead_id' => $this->lead->id,
            'type' => 'call',
            'title' => 'Hasta ile görüşüldü',
            'description' => 'Hasta tedavi hakkında bilgi aldı.',
            'occurred_at' => '2026-10-07 14:30:00',
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
            'data.crm_lead_id',
            $this->lead->id
        )
        ->assertJsonPath(
            'data.business_user_id',
            $this->businessUser->id
        )
        ->assertJsonPath(
            'data.type',
            'call'
        )
        ->assertJsonPath(
            'data.title',
            'Hasta ile görüşüldü'
        );

    $this->assertDatabaseHas('crm_activities', [
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'crm_lead_id' => $this->lead->id,
        'business_user_id' => $this->businessUser->id,
        'type' => 'call',
        'title' => 'Hasta ile görüşüldü',
    ]);
});

test('crm activity uses current time when occurred at is omitted', function () {
    $before = now()->subSecond();

    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/activities",
        [
            'crm_lead_id' => $this->lead->id,
            'type' => 'note',
            'title' => 'Not oluşturuldu',
        ]
    );

    $after = now()->addSecond();

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.type',
            'note'
        );

    $activity = CrmActivity::query()->latest('id')->first();

    expect($activity->occurred_at)->not->toBeNull();
    expect($activity->occurred_at)->toBeBetween(
        $before,
        $after
    );
});

test('activity automatically uses authenticated business user', function () {
    $otherBusinessUser = BusinessUser::create([
        'business_id' => $this->business->id,
        'user_id' => User::factory()->create()->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/activities",
        [
            'crm_lead_id' => $this->lead->id,
            'type' => 'note',
            'title' => 'Personel notu',
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.business_user_id',
            $this->businessUser->id
        );

    expect(
        $response->json('data.business_user_id')
    )->not->toBe($otherBusinessUser->id);
});

test('cannot create activity using lead from another branch', function () {
    $otherLead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->otherBranch->id,
        'first_name' => 'Diğer',
        'last_name' => 'Lead',
        'status' => 'new',
    ]);

    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/activities",
        [
            'crm_lead_id' => $otherLead->id,
            'type' => 'call',
            'title' => 'Yetkisiz aktivite',
        ]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonPath(
            'success',
            false
        );

    $this->assertDatabaseMissing('crm_activities', [
        'crm_lead_id' => $otherLead->id,
        'title' => 'Yetkisiz aktivite',
    ]);
});

test('cannot create activity when authenticated business user has no active branch access', function () {
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
        "/api/branches/{$this->branch->id}/crm/activities",
        [
            'crm_lead_id' => $this->lead->id,
            'type' => 'note',
            'title' => 'Yetkisiz şube aktivitesi',
        ]
    );

    $response
        ->assertForbidden()
        ->assertJsonPath(
            'success',
            false
        );
});

test('can show crm activity', function () {
    $activity = CrmActivity::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'crm_lead_id' => $this->lead->id,
        'business_user_id' => $this->businessUser->id,
        'type' => 'meeting',
        'title' => 'Ön görüşme',
        'description' => 'Online görüşme yapıldı.',
        'occurred_at' => now(),
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/activities/{$activity->id}"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'success',
            true
        )
        ->assertJsonPath(
            'data.id',
            $activity->id
        )
        ->assertJsonPath(
            'data.title',
            'Ön görüşme'
        )
        ->assertJsonPath(
            'data.lead.id',
            $this->lead->id
        )
        ->assertJsonPath(
            'data.business_user.id',
            $this->businessUser->id
        );
});

test('cannot show activity belonging to another branch', function () {
    $otherLead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->otherBranch->id,
        'first_name' => 'Diğer',
        'last_name' => 'Lead',
        'status' => 'new',
    ]);

    $activity = CrmActivity::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->otherBranch->id,
        'crm_lead_id' => $otherLead->id,
        'business_user_id' => $this->businessUser->id,
        'type' => 'note',
        'title' => 'Diğer şube aktivitesi',
        'occurred_at' => now(),
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/activities/{$activity->id}"
    );

    $response
        ->assertNotFound()
        ->assertJsonPath(
            'success',
            false
        );
});

test('can update crm activity', function () {
    $activity = CrmActivity::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'crm_lead_id' => $this->lead->id,
        'business_user_id' => $this->businessUser->id,
        'type' => 'call',
        'title' => 'Eski başlık',
        'description' => 'Eski açıklama',
        'occurred_at' => now(),
    ]);

    $response = $this->putJson(
        "/api/branches/{$this->branch->id}/crm/activities/{$activity->id}",
        [
            'title' => 'Yeni başlık',
            'description' => 'Yeni açıklama',
            'type' => 'whatsapp',
        ]
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'success',
            true
        )
        ->assertJsonPath(
            'data.title',
            'Yeni başlık'
        )
        ->assertJsonPath(
            'data.description',
            'Yeni açıklama'
        )
        ->assertJsonPath(
            'data.type',
            'whatsapp'
        );
});

test('cannot update activity with lead from another branch', function () {
    $activity = CrmActivity::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'crm_lead_id' => $this->lead->id,
        'business_user_id' => $this->businessUser->id,
        'type' => 'call',
        'title' => 'Lead değiştirilecek',
        'occurred_at' => now(),
    ]);

    $otherLead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->otherBranch->id,
        'first_name' => 'Diğer',
        'last_name' => 'Lead',
        'status' => 'new',
    ]);

    $response = $this->putJson(
        "/api/branches/{$this->branch->id}/crm/activities/{$activity->id}",
        [
            'crm_lead_id' => $otherLead->id,
        ]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonPath(
            'success',
            false
        );

    expect($activity->fresh()->crm_lead_id)
        ->toBe($this->lead->id);
});

test('can delete crm activity', function () {
    $activity = CrmActivity::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'crm_lead_id' => $this->lead->id,
        'business_user_id' => $this->businessUser->id,
        'type' => 'note',
        'title' => 'Silinecek aktivite',
        'occurred_at' => now(),
    ]);

    $response = $this->deleteJson(
        "/api/branches/{$this->branch->id}/crm/activities/{$activity->id}"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'success',
            true
        );

    $this->assertDatabaseMissing('crm_activities', [
        'id' => $activity->id,
    ]);
});

test('cannot delete activity belonging to another branch', function () {
    $otherLead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->otherBranch->id,
        'first_name' => 'Diğer',
        'last_name' => 'Lead',
        'status' => 'new',
    ]);

    $activity = CrmActivity::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->otherBranch->id,
        'crm_lead_id' => $otherLead->id,
        'business_user_id' => $this->businessUser->id,
        'type' => 'note',
        'title' => 'Silinmemesi gereken aktivite',
        'occurred_at' => now(),
    ]);

    $response = $this->deleteJson(
        "/api/branches/{$this->branch->id}/crm/activities/{$activity->id}"
    );

    $response
        ->assertNotFound()
        ->assertJsonPath(
            'success',
            false
        );

    $this->assertDatabaseHas('crm_activities', [
        'id' => $activity->id,
    ]);
});