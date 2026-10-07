<?php

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
        );

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