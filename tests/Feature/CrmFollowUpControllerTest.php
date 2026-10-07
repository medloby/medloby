<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\CrmFollowUp;
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

test('can list crm follow ups for branch', function () {
    $followUp = CrmFollowUp::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'crm_lead_id' => $this->lead->id,
        'assigned_business_user_id' => $this->businessUser->id,
        'type' => 'call',
        'title' => 'Hasta aranacak',
        'notes' => 'Fiyat hakkında tekrar görüşülecek.',
        'scheduled_at' => now()->addDay(),
        'status' => 'pending',
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/follow-ups"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $followUp->id)
        ->assertJsonPath('data.0.title', 'Hasta aranacak')
        ->assertJsonPath('data.0.status', 'pending');
});

test('cannot list follow ups belonging to another branch', function () {
    $otherLead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->otherBranch->id,
        'first_name' => 'Diğer',
        'last_name' => 'Lead',
        'status' => 'new',
    ]);

    CrmFollowUp::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->otherBranch->id,
        'crm_lead_id' => $otherLead->id,
        'assigned_business_user_id' => null,
        'type' => 'call',
        'title' => 'Diğer şube takibi',
        'scheduled_at' => now()->addDay(),
        'status' => 'pending',
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/follow-ups"
    );

    $response
        ->assertSuccessful()
        ->assertJsonCount(0, 'data');
});

test('can filter follow ups by lead', function () {
    $otherLead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'first_name' => 'Mehmet',
        'last_name' => 'Kaya',
        'status' => 'new',
    ]);

    $leadFollowUp = CrmFollowUp::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'crm_lead_id' => $this->lead->id,
        'assigned_business_user_id' => $this->businessUser->id,
        'type' => 'call',
        'title' => 'Ahmet takip',
        'scheduled_at' => now()->addDay(),
        'status' => 'pending',
    ]);

    CrmFollowUp::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'crm_lead_id' => $otherLead->id,
        'assigned_business_user_id' => $this->businessUser->id,
        'type' => 'note',
        'title' => 'Mehmet takip',
        'scheduled_at' => now()->addDay(),
        'status' => 'pending',
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/follow-ups?crm_lead_id={$this->lead->id}"
    );

    $response
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $leadFollowUp->id);
});

test('can filter follow ups by status', function () {
    $pendingFollowUp = CrmFollowUp::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'crm_lead_id' => $this->lead->id,
        'assigned_business_user_id' => $this->businessUser->id,
        'type' => 'call',
        'title' => 'Bekleyen takip',
        'scheduled_at' => now()->addDay(),
        'status' => 'pending',
    ]);

    CrmFollowUp::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'crm_lead_id' => $this->lead->id,
        'assigned_business_user_id' => $this->businessUser->id,
        'type' => 'email',
        'title' => 'Tamamlanan takip',
        'scheduled_at' => now()->subDay(),
        'completed_at' => now(),
        'status' => 'completed',
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/follow-ups?status=pending"
    );

    $response
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $pendingFollowUp->id)
        ->assertJsonPath('data.0.status', 'pending');
});

test('can create crm follow up', function () {
    $scheduledAt = now()->addDay()->setSecond(0);

    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/follow-ups",
        [
            'crm_lead_id' => $this->lead->id,
            'assigned_business_user_id' => $this->businessUser->id,
            'type' => 'call',
            'title' => 'Hasta tekrar aranacak',
            'notes' => 'Tedavi kararını sormak için aranacak.',
            'scheduled_at' => $scheduledAt->toDateTimeString(),
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
            'data.assigned_business_user_id',
            $this->businessUser->id
        )
        ->assertJsonPath('data.type', 'call')
        ->assertJsonPath('data.title', 'Hasta tekrar aranacak')
        ->assertJsonPath('data.status', 'pending');

    $this->assertDatabaseHas('crm_follow_ups', [
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'crm_lead_id' => $this->lead->id,
        'assigned_business_user_id' => $this->businessUser->id,
        'type' => 'call',
        'title' => 'Hasta tekrar aranacak',
        'status' => 'pending',
    ]);
});

test('can create crm follow up without assigned business user', function () {
    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/follow-ups",
        [
            'crm_lead_id' => $this->lead->id,
            'type' => 'email',
            'title' => 'E-posta takibi',
            'scheduled_at' => now()->addDay()->toDateTimeString(),
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.assigned_business_user_id',
            null
        )
        ->assertJsonPath(
            'data.status',
            'pending'
        );
});

test('cannot create follow up using lead from another branch', function () {
    $otherLead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->otherBranch->id,
        'first_name' => 'Diğer',
        'last_name' => 'Lead',
        'status' => 'new',
    ]);

    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/follow-ups",
        [
            'crm_lead_id' => $otherLead->id,
            'type' => 'call',
            'title' => 'Yetkisiz takip',
            'scheduled_at' => now()->addDay()->toDateTimeString(),
        ]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonPath('success', false);

    $this->assertDatabaseMissing('crm_follow_ups', [
        'crm_lead_id' => $otherLead->id,
        'title' => 'Yetkisiz takip',
    ]);
});

test('cannot create follow up assigning business user from another branch', function () {
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

    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/follow-ups",
        [
            'crm_lead_id' => $this->lead->id,
            'assigned_business_user_id' => $otherBusinessUser->id,
            'type' => 'call',
            'title' => 'Yetkisiz personel takibi',
            'scheduled_at' => now()->addDay()->toDateTimeString(),
        ]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonPath('success', false);

    $this->assertDatabaseMissing('crm_follow_ups', [
        'assigned_business_user_id' => $otherBusinessUser->id,
        'title' => 'Yetkisiz personel takibi',
    ]);
});

test('can show crm follow up', function () {
    $followUp = CrmFollowUp::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'crm_lead_id' => $this->lead->id,
        'assigned_business_user_id' => $this->businessUser->id,
        'type' => 'meeting',
        'title' => 'Görüşme takibi',
        'notes' => 'Klinikte görüşme yapılacak.',
        'scheduled_at' => now()->addDay(),
        'status' => 'pending',
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/follow-ups/{$followUp->id}"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $followUp->id)
        ->assertJsonPath('data.title', 'Görüşme takibi')
        ->assertJsonPath('data.lead.id', $this->lead->id)
        ->assertJsonPath(
            'data.assigned_business_user.id',
            $this->businessUser->id
        );
});

test('cannot show follow up belonging to another branch', function () {
    $otherLead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->otherBranch->id,
        'first_name' => 'Diğer',
        'last_name' => 'Lead',
        'status' => 'new',
    ]);

    $followUp = CrmFollowUp::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->otherBranch->id,
        'crm_lead_id' => $otherLead->id,
        'assigned_business_user_id' => null,
        'type' => 'call',
        'title' => 'Diğer şube takibi',
        'scheduled_at' => now()->addDay(),
        'status' => 'pending',
    ]);

    $response = $this->getJson(
        "/api/branches/{$this->branch->id}/crm/follow-ups/{$followUp->id}"
    );

    $response
        ->assertNotFound()
        ->assertJsonPath('success', false);
});

test('can update crm follow up', function () {
    $followUp = CrmFollowUp::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'crm_lead_id' => $this->lead->id,
        'assigned_business_user_id' => null,
        'type' => 'call',
        'title' => 'Eski başlık',
        'notes' => 'Eski not',
        'scheduled_at' => now()->addDay(),
        'status' => 'pending',
    ]);

    $response = $this->putJson(
        "/api/branches/{$this->branch->id}/crm/follow-ups/{$followUp->id}",
        [
            'assigned_business_user_id' => $this->businessUser->id,
            'type' => 'whatsapp',
            'title' => 'Yeni başlık',
            'notes' => 'Yeni not',
        ]
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath(
            'data.assigned_business_user_id',
            $this->businessUser->id
        )
        ->assertJsonPath('data.type', 'whatsapp')
        ->assertJsonPath('data.title', 'Yeni başlık')
        ->assertJsonPath('data.notes', 'Yeni not');
});

test('cannot update follow up with lead from another branch', function () {
    $followUp = CrmFollowUp::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'crm_lead_id' => $this->lead->id,
        'assigned_business_user_id' => null,
        'type' => 'call',
        'title' => 'Lead değiştirilecek',
        'scheduled_at' => now()->addDay(),
        'status' => 'pending',
    ]);

    $otherLead = CrmLead::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->otherBranch->id,
        'first_name' => 'Diğer',
        'last_name' => 'Lead',
        'status' => 'new',
    ]);

    $response = $this->putJson(
        "/api/branches/{$this->branch->id}/crm/follow-ups/{$followUp->id}",
        [
            'crm_lead_id' => $otherLead->id,
        ]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonPath('success', false);

    expect($followUp->fresh()->crm_lead_id)
        ->toBe($this->lead->id);
});

test('cannot update follow up with business user from another branch', function () {
    $followUp = CrmFollowUp::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'crm_lead_id' => $this->lead->id,
        'assigned_business_user_id' => null,
        'type' => 'call',
        'title' => 'Personel değiştirilecek',
        'scheduled_at' => now()->addDay(),
        'status' => 'pending',
    ]);

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

    $response = $this->putJson(
        "/api/branches/{$this->branch->id}/crm/follow-ups/{$followUp->id}",
        [
            'assigned_business_user_id' => $otherBusinessUser->id,
        ]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonPath('success', false);

    expect($followUp->fresh()->assigned_business_user_id)
        ->toBeNull();
});

test('can complete crm follow up', function () {
    $followUp = CrmFollowUp::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'crm_lead_id' => $this->lead->id,
        'assigned_business_user_id' => $this->businessUser->id,
        'type' => 'call',
        'title' => 'Tamamlanacak takip',
        'scheduled_at' => now()->addDay(),
        'status' => 'pending',
    ]);

    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/follow-ups/{$followUp->id}/complete"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'completed');

    $followUp->refresh();

    expect($followUp->status)
        ->toBe('completed');

    expect($followUp->completed_at)
        ->not->toBeNull();
});

test('cannot complete already completed crm follow up', function () {
    $followUp = CrmFollowUp::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'crm_lead_id' => $this->lead->id,
        'assigned_business_user_id' => $this->businessUser->id,
        'type' => 'call',
        'title' => 'Zaten tamamlandı',
        'scheduled_at' => now()->addDay(),
        'completed_at' => now(),
        'status' => 'completed',
    ]);

    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/follow-ups/{$followUp->id}/complete"
    );

    $response
        ->assertUnprocessable()
        ->assertJsonPath('success', false);
});

test('can cancel crm follow up', function () {
    $followUp = CrmFollowUp::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'crm_lead_id' => $this->lead->id,
        'assigned_business_user_id' => $this->businessUser->id,
        'type' => 'email',
        'title' => 'İptal edilecek takip',
        'scheduled_at' => now()->addDay(),
        'status' => 'pending',
    ]);

    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/follow-ups/{$followUp->id}/cancel"
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'cancelled');

    $followUp->refresh();

    expect($followUp->status)
        ->toBe('cancelled');

    expect($followUp->cancelled_at)
        ->not->toBeNull();
});

test('cannot cancel completed crm follow up', function () {
    $followUp = CrmFollowUp::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'crm_lead_id' => $this->lead->id,
        'assigned_business_user_id' => $this->businessUser->id,
        'type' => 'call',
        'title' => 'Tamamlanmış takip',
        'scheduled_at' => now()->addDay(),
        'completed_at' => now(),
        'status' => 'completed',
    ]);

    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/follow-ups/{$followUp->id}/cancel"
    );

    $response
        ->assertUnprocessable()
        ->assertJsonPath('success', false);
});

test('cannot cancel already cancelled crm follow up', function () {
    $followUp = CrmFollowUp::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'crm_lead_id' => $this->lead->id,
        'assigned_business_user_id' => $this->businessUser->id,
        'type' => 'call',
        'title' => 'Zaten iptal edildi',
        'scheduled_at' => now()->addDay(),
        'cancelled_at' => now(),
        'status' => 'cancelled',
    ]);

    $response = $this->postJson(
        "/api/branches/{$this->branch->id}/crm/follow-ups/{$followUp->id}/cancel"
    );

    $response
        ->assertUnprocessable()
        ->assertJsonPath('success', false);
});