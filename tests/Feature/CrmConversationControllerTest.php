<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Conversation;
use App\Models\CrmLead;
use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createCrmConversationTestBusiness(): Business
{
    return Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
    ]);
}

function createCrmConversationTestBranch(Business $business, string $suffix = ''): Branch
{
    return Branch::create([
        'business_id' => $business->id,
        'name' => 'Test Şubesi '.$suffix,
        'slug' => 'test-subesi-'.uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
    ]);
}

function createCrmConversationTestPatient(): PatientProfile
{
    $user = User::factory()->create();

    return PatientProfile::create([
        'user_id' => $user->id,
        'first_name' => 'Test',
        'last_name' => 'Hasta',
        'phone' => '05550000000',
    ]);
}

function createCrmConversationTestBusinessUser(
    Business $business,
    Branch $branch,
    bool $active = true
): array {
    $user = User::factory()->create();

    $businessUser = BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'role' => 'staff',
        'is_active' => $active,
    ]);

    $businessUser->branches()->attach(
        $branch->id,
        [
            'is_active' => $active,
        ]
    );

    return [
        'user' => $user,
        'businessUser' => $businessUser,
    ];
}

function createCrmConversationTestLead(
    Business $business,
    Branch $branch,
    PatientProfile $patient
): CrmLead {
    return CrmLead::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'patient_profile_id' => $patient->id,
        'first_name' => 'Test',
        'last_name' => 'Lead',
        'source' => 'manual',
        'status' => 'new',
        'priority' => 'normal',
    ]);
}

function createCrmConversationTestConversation(
    Business $business,
    Branch $branch,
    PatientProfile $patient
): Conversation {
    return Conversation::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'patient_profile_id' => $patient->id,
        'subject' => 'Test görüşmesi',
        'status' => 'open',
    ]);
}

function attachLeadUrl(
    Branch $branch,
    CrmLead $lead,
    Conversation $conversation
): string {
    return "/api/branches/{$branch->id}/crm/leads/{$lead->id}/conversations/{$conversation->id}/attach";
}

test('authorized business user can attach conversation to crm lead', function () {
    $business = createCrmConversationTestBusiness();
    $branch = createCrmConversationTestBranch($business);

    $patient = createCrmConversationTestPatient();

    $lead = createCrmConversationTestLead(
        $business,
        $branch,
        $patient
    );

    $conversation = createCrmConversationTestConversation(
        $business,
        $branch,
        $patient
    );

    $membership = createCrmConversationTestBusinessUser(
        $business,
        $branch
    );

    $response = $this->actingAs($membership['user'])
        ->postJson(
            attachLeadUrl(
                $branch,
                $lead,
                $conversation
            )
        );

    $response
        ->assertSuccessful()
        ->assertJsonPath(
            'data.crm_lead.id',
            $lead->id
        )
        ->assertJsonPath('data.crm_lead_id', $lead->id)
        ->assertJsonPath('data.patient_profile.id', $patient->id)
        ->assertJsonMissingPath('data.patient_profile.phone');

    expect($conversation->fresh()->crm_lead_id)
        ->toBe($lead->id);
});

test('cannot attach lead from another business', function () {
    $business = createCrmConversationTestBusiness();
    $branch = createCrmConversationTestBranch($business);

    $otherBusiness = createCrmConversationTestBusiness();
    $otherBranch = createCrmConversationTestBranch(
        $otherBusiness,
        'Other'
    );

    $patient = createCrmConversationTestPatient();

    $lead = createCrmConversationTestLead(
        $otherBusiness,
        $otherBranch,
        $patient
    );

    $conversation = createCrmConversationTestConversation(
        $business,
        $branch,
        $patient
    );

    $membership = createCrmConversationTestBusinessUser(
        $business,
        $branch
    );

    $this->actingAs($membership['user'])
        ->postJson(
            attachLeadUrl(
                $branch,
                $lead,
                $conversation
            )
        )
        ->assertNotFound();

    expect($conversation->fresh()->crm_lead_id)
        ->toBeNull();
});

test('lists attachable conversations within the branch and links a patient only on explicit selection', function () {
    $business = createCrmConversationTestBusiness();
    $branch = createCrmConversationTestBranch($business);
    $otherBranch = createCrmConversationTestBranch($business, '-other');
    $patient = createCrmConversationTestPatient();
    $otherPatient = createCrmConversationTestPatient();
    $staff = createCrmConversationTestBusinessUser($business, $branch);
    $lead = CrmLead::create(['business_id' => $business->id, 'branch_id' => $branch->id]);
    $otherLead = createCrmConversationTestLead($business, $branch, $patient);
    $conversation = createCrmConversationTestConversation($business, $branch, $patient);
    $otherConversation = createCrmConversationTestConversation($business, $branch, $otherPatient);
    $outside = createCrmConversationTestConversation($business, $otherBranch, $patient);
    $attached = createCrmConversationTestConversation($business, $branch, $patient);
    $attached->update(['crm_lead_id' => $otherLead->id]);
    $list = "/api/branches/{$branch->id}/crm/leads/{$lead->id}/conversations";
    $attach = attachLeadUrl($branch, $lead, $conversation);

    $this->actingAs($staff['user'])->getJson($list)->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $otherConversation->id)
        ->assertJsonPath('data.1.id', $conversation->id)
        ->assertJsonMissing(['id' => $outside->id])
        ->assertJsonMissing(['id' => $attached->id]);
    $this->postJson($attach, [])->assertUnprocessable();
    $this->postJson(attachLeadUrl($branch, $lead, $attached), ['link_patient' => true])->assertUnprocessable();
    $this->postJson($attach, ['link_patient' => true])->assertOk();
    $this->assertDatabaseHas('crm_leads', ['id' => $lead->id, 'patient_profile_id' => $patient->id]);
    $this->getJson($list)->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $conversation->id)
        ->assertJsonPath('data.0.attached', true);
    $this->postJson(attachLeadUrl($branch, $lead, $otherConversation), ['link_patient' => true])->assertUnprocessable();
});

test('staff can correct a linked conversation and patient while preserving their original records', function () {
    $business = createCrmConversationTestBusiness();
    $branch = createCrmConversationTestBranch($business);
    $staff = createCrmConversationTestBusinessUser($business, $branch);
    $patient = createCrmConversationTestPatient();
    $otherPatient = createCrmConversationTestPatient();
    $lead = createCrmConversationTestLead($business, $branch, $patient);
    $otherLead = createCrmConversationTestLead($business, $branch, $patient);
    $conversation = createCrmConversationTestConversation($business, $branch, $patient);
    $conversation->update(['crm_lead_id' => $lead->id]);
    $otherConversation = createCrmConversationTestConversation($business, $branch, $otherPatient);
    $message = $conversation->messages()->create(['sender_user_id' => $patient->user_id, 'body' => 'Randevu görüşmesi', 'message_type' => 'text']);
    $offer = $conversation->offers()->create([
        'business_id' => $business->id, 'branch_id' => $branch->id, 'patient_profile_id' => $patient->id,
        'created_by' => $staff['user']->id, 'title' => 'Tedavi teklifi', 'amount' => 200, 'currency' => 'TRY', 'status' => 'accepted',
    ]);
    $appointment = Appointment::create([
        'business_id' => $business->id, 'branch_id' => $branch->id, 'patient_profile_id' => $patient->id,
        'offer_id' => $offer->id, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(), 'status' => 'confirmed',
    ]);
    $leadPath = "/api/branches/{$branch->id}/crm/leads/{$lead->id}";
    $link = "{$leadPath}/conversations/{$conversation->id}";

    $this->actingAs($staff['user'])->deleteJson($link)->assertOk()->assertJsonPath('success', true);
    $this->deleteJson($link)->assertOk();
    $this->getJson("{$leadPath}/conversations")->assertOk()->assertJsonPath('data.0.attached', false);
    expect($lead->refresh()->patient_profile_id)->toBe($patient->id);

    $this->postJson(attachLeadUrl($branch, $otherLead, $conversation))->assertOk();
    $this->deleteJson($link)->assertConflict();
    expect($conversation->refresh()->crm_lead_id)->toBe($otherLead->id);

    $this->putJson($leadPath, ['patient_profile_id' => null])->assertOk()->assertJsonPath('data.patient_profile_id', null);
    $this->postJson(attachLeadUrl($branch, $lead, $otherConversation), ['link_patient' => true])->assertOk();
    expect($lead->refresh()->patient_profile_id)->toBe($otherPatient->id);

    $this->assertDatabaseHas('messages', ['id' => $message->id, 'conversation_id' => $conversation->id]);
    $this->assertDatabaseHas('offers', ['id' => $offer->id, 'conversation_id' => $conversation->id, 'patient_profile_id' => $patient->id]);
    $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'offer_id' => $offer->id, 'patient_profile_id' => $patient->id]);
});

test('legacy mismatches can be unlinked within the branch without exposing the mismatched conversation details', function () {
    $business = createCrmConversationTestBusiness();
    $branch = createCrmConversationTestBranch($business);
    $otherBranch = createCrmConversationTestBranch($business, '-other');
    $staff = createCrmConversationTestBusinessUser($business, $branch);
    $patient = createCrmConversationTestPatient();
    $otherPatient = createCrmConversationTestPatient();
    $lead = createCrmConversationTestLead($business, $branch, $patient);
    $legacy = createCrmConversationTestConversation($business, $branch, $otherPatient);
    $legacy->update(['crm_lead_id' => $lead->id, 'subject' => 'Başka hasta görüşmesi']);
    $outside = createCrmConversationTestConversation($business, $otherBranch, $patient);
    $outside->update(['crm_lead_id' => $lead->id]);
    $path = "/api/branches/{$branch->id}/crm/leads/{$lead->id}";

    $this->actingAs($staff['user'])->getJson("{$path}/conversations")
        ->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0', ['id' => $legacy->id, 'subject' => null, 'patient_name' => null, 'attached' => true, 'can_open' => false]);
    $this->putJson($path, ['patient_profile_id' => null])->assertUnprocessable()->assertJsonValidationErrors(['patient_profile_id']);
    $this->deleteJson("{$path}/conversations/{$outside->id}")->assertNotFound();
    $staff['businessUser']->branches()->updateExistingPivot($branch->id, ['is_active' => false]);
    $this->deleteJson("{$path}/conversations/{$legacy->id}")->assertForbidden();
    $staff['businessUser']->branches()->updateExistingPivot($branch->id, ['is_active' => true]);
    $this->deleteJson("{$path}/conversations/{$legacy->id}")->assertOk();
    expect($legacy->refresh()->crm_lead_id)->toBeNull();
    expect($outside->refresh()->crm_lead_id)->toBe($lead->id);
});

test('cannot attach lead from another branch', function () {
    $business = createCrmConversationTestBusiness();

    $branch = createCrmConversationTestBranch(
        $business,
        'Main'
    );

    $otherBranch = createCrmConversationTestBranch(
        $business,
        'Other'
    );

    $patient = createCrmConversationTestPatient();

    $lead = createCrmConversationTestLead(
        $business,
        $otherBranch,
        $patient
    );

    $conversation = createCrmConversationTestConversation(
        $business,
        $branch,
        $patient
    );

    $membership = createCrmConversationTestBusinessUser(
        $business,
        $branch
    );

    $this->actingAs($membership['user'])
        ->postJson(
            attachLeadUrl(
                $branch,
                $lead,
                $conversation
            )
        )
        ->assertNotFound();

    expect($conversation->fresh()->crm_lead_id)
        ->toBeNull();
});

test('cannot attach conversation from another business', function () {
    $business = createCrmConversationTestBusiness();
    $branch = createCrmConversationTestBranch($business);

    $otherBusiness = createCrmConversationTestBusiness();
    $otherBranch = createCrmConversationTestBranch(
        $otherBusiness,
        'Other'
    );

    $patient = createCrmConversationTestPatient();

    $lead = createCrmConversationTestLead(
        $business,
        $branch,
        $patient
    );

    $conversation = createCrmConversationTestConversation(
        $otherBusiness,
        $otherBranch,
        $patient
    );

    $membership = createCrmConversationTestBusinessUser(
        $business,
        $branch
    );

    $this->actingAs($membership['user'])
        ->postJson(
            attachLeadUrl(
                $branch,
                $lead,
                $conversation
            )
        )
        ->assertNotFound();

    expect($conversation->fresh()->crm_lead_id)
        ->toBeNull();
});

test('cannot attach conversation from another branch', function () {
    $business = createCrmConversationTestBusiness();

    $branch = createCrmConversationTestBranch(
        $business,
        'Main'
    );

    $otherBranch = createCrmConversationTestBranch(
        $business,
        'Other'
    );

    $patient = createCrmConversationTestPatient();

    $lead = createCrmConversationTestLead(
        $business,
        $branch,
        $patient
    );

    $conversation = createCrmConversationTestConversation(
        $business,
        $otherBranch,
        $patient
    );

    $membership = createCrmConversationTestBusinessUser(
        $business,
        $branch
    );

    $this->actingAs($membership['user'])
        ->postJson(
            attachLeadUrl(
                $branch,
                $lead,
                $conversation
            )
        )
        ->assertNotFound();

    expect($conversation->fresh()->crm_lead_id)
        ->toBeNull();
});

test('cannot attach conversation belonging to another patient', function () {
    $business = createCrmConversationTestBusiness();
    $branch = createCrmConversationTestBranch($business);

    $leadPatient = createCrmConversationTestPatient();
    $otherPatient = createCrmConversationTestPatient();

    $lead = createCrmConversationTestLead(
        $business,
        $branch,
        $leadPatient
    );

    $conversation = createCrmConversationTestConversation(
        $business,
        $branch,
        $otherPatient
    );

    $membership = createCrmConversationTestBusinessUser(
        $business,
        $branch
    );

    $this->actingAs($membership['user'])
        ->postJson(
            attachLeadUrl(
                $branch,
                $lead,
                $conversation
            )
        )
        ->assertUnprocessable();

    expect($conversation->fresh()->crm_lead_id)
        ->toBeNull();
});

test('unauthorized business user cannot attach conversation to crm lead', function () {
    $business = createCrmConversationTestBusiness();
    $branch = createCrmConversationTestBranch($business);

    $patient = createCrmConversationTestPatient();

    $lead = createCrmConversationTestLead(
        $business,
        $branch,
        $patient
    );

    $conversation = createCrmConversationTestConversation(
        $business,
        $branch,
        $patient
    );

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(
            attachLeadUrl(
                $branch,
                $lead,
                $conversation
            )
        )
        ->assertForbidden();

    expect($conversation->fresh()->crm_lead_id)
        ->toBeNull();
});

test('inactive branch membership cannot attach conversation to crm lead', function () {
    $business = createCrmConversationTestBusiness();
    $branch = createCrmConversationTestBranch($business);

    $patient = createCrmConversationTestPatient();

    $lead = createCrmConversationTestLead(
        $business,
        $branch,
        $patient
    );

    $conversation = createCrmConversationTestConversation(
        $business,
        $branch,
        $patient
    );

    $membership = createCrmConversationTestBusinessUser(
        $business,
        $branch,
        false
    );

    $this->actingAs($membership['user'])
        ->postJson(
            attachLeadUrl(
                $branch,
                $lead,
                $conversation
            )
        )
        ->assertForbidden();

    expect($conversation->fresh()->crm_lead_id)
        ->toBeNull();
});
