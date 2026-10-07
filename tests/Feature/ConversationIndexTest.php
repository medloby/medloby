<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Conversation;
use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createConversationIndexData(): array
{
    $business = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
    ]);

    $otherBusiness = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
    ]);

    $branch = Branch::create([
        'business_id' => $business->id,
        'name' => 'Test Şube',
        'slug' => 'test-sube-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
    ]);

    $otherBranch = Branch::create([
        'business_id' => $otherBusiness->id,
        'name' => 'Diğer Şube',
        'slug' => 'diger-sube-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Besiktas',
        'status' => 'active',
    ]);

    $patientUser = User::factory()->create();

    $patientProfile = PatientProfile::create([
        'user_id' => $patientUser->id,
        'first_name' => 'Test',
        'last_name' => 'Hasta',
        'phone' => '05550000000',
    ]);

    $businessUser = User::factory()->create();

    BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $businessUser->id,
        'role' => 'owner',
        'is_active' => true,
    ]);

    return [
        'business' => $business,
        'otherBusiness' => $otherBusiness,
        'branch' => $branch,
        'otherBranch' => $otherBranch,
        'patientUser' => $patientUser,
        'patientProfile' => $patientProfile,
        'businessUser' => $businessUser,
    ];
}

function createIndexConversation(
    array $data,
    int $businessId,
    int $patientProfileId,
    ?int $branchId = null,
    ?string $subject = null
): Conversation {
    return Conversation::create([
        'business_id' => $businessId,
        'branch_id' => $branchId,
        'patient_profile_id' => $patientProfileId,
        'subject' => $subject ?? 'Test görüşmesi',
        'status' => 'open',
    ]);
}

test('patient can list their own conversations', function () {
    $data = createConversationIndexData();

    $ownConversation = createIndexConversation(
        $data,
        $data['business']->id,
        $data['patientProfile']->id,
        $data['branch']->id,
        'Benim görüşmem'
    );

    $otherPatient = User::factory()->create();

    $otherPatientProfile = PatientProfile::create([
        'user_id' => $otherPatient->id,
        'first_name' => 'Başka',
        'last_name' => 'Hasta',
        'phone' => '05551111111',
    ]);

    createIndexConversation(
        $data,
        $data['business']->id,
        $otherPatientProfile->id,
        $data['branch']->id,
        'Başkasının görüşmesi'
    );

    $response = $this
        ->actingAs($data['patientUser'])
        ->getJson('/api/conversations');

    $response->assertOk();

    $response->assertJson([
        'success' => true,
    ]);

    $response->assertJsonCount(
        1,
        'data.data'
    );

    $response->assertJsonFragment([
        'id' => $ownConversation->id,
        'subject' => 'Benim görüşmem',
    ]);

    $response->assertJsonMissing([
        'subject' => 'Başkasının görüşmesi',
    ]);
});

test('patient cannot see conversations belonging to another business when they are not the patient', function () {
    $data = createConversationIndexData();

    $ownConversation = createIndexConversation(
        $data,
        $data['business']->id,
        $data['patientProfile']->id,
        $data['branch']->id,
        'Kendi görüşmem'
    );

    $otherPatient = User::factory()->create();

    $otherPatientProfile = PatientProfile::create([
        'user_id' => $otherPatient->id,
        'first_name' => 'Başka',
        'last_name' => 'Hasta',
        'phone' => '05552222222',
    ]);

    createIndexConversation(
        $data,
        $data['otherBusiness']->id,
        $otherPatientProfile->id,
        $data['otherBranch']->id,
        'Diğer business görüşmesi'
    );

    $response = $this
        ->actingAs($data['patientUser'])
        ->getJson('/api/conversations');

    $response->assertOk();

    $response->assertJsonCount(
        1,
        'data.data'
    );

    $response->assertJsonFragment([
        'id' => $ownConversation->id,
    ]);

    $response->assertJsonMissing([
        'subject' => 'Diğer business görüşmesi',
    ]);
});

test('business user can list conversations belonging to their business', function () {
    $data = createConversationIndexData();

    $ownBusinessConversation = createIndexConversation(
        $data,
        $data['business']->id,
        $data['patientProfile']->id,
        $data['branch']->id,
        'Business görüşmesi'
    );

    $otherBusinessPatient = User::factory()->create();

    $otherBusinessPatientProfile = PatientProfile::create([
        'user_id' => $otherBusinessPatient->id,
        'first_name' => 'Diğer',
        'last_name' => 'Hasta',
        'phone' => '05553333333',
    ]);

    $otherBusinessConversation = createIndexConversation(
        $data,
        $data['otherBusiness']->id,
        $otherBusinessPatientProfile->id,
        $data['otherBranch']->id,
        'Başka business görüşmesi'
    );

    $response = $this
        ->actingAs($data['businessUser'])
        ->getJson('/api/conversations');

    $response->assertOk();

    $response->assertJsonCount(
        1,
        'data.data'
    );

    $response->assertJsonFragment([
        'id' => $ownBusinessConversation->id,
        'subject' => 'Business görüşmesi',
    ]);

    $response->assertJsonMissing([
        'id' => $otherBusinessConversation->id,
    ]);
});

test('inactive business member cannot see business conversations through membership', function () {
    $data = createConversationIndexData();

    createIndexConversation(
        $data,
        $data['business']->id,
        $data['patientProfile']->id,
        $data['branch']->id,
        'Business görüşmesi'
    );

    $inactiveUser = User::factory()->create();

    BusinessUser::create([
        'business_id' => $data['business']->id,
        'user_id' => $inactiveUser->id,
        'role' => 'staff',
        'is_active' => false,
    ]);

    $response = $this
        ->actingAs($inactiveUser)
        ->getJson('/api/conversations');

    $response->assertOk();

    $response->assertJsonCount(
        0,
        'data.data'
    );
});

test('user without patient profile and business membership sees no conversations', function () {
    $data = createConversationIndexData();

    createIndexConversation(
        $data,
        $data['business']->id,
        $data['patientProfile']->id,
        $data['branch']->id
    );

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->getJson('/api/conversations');

    $response->assertOk();

    $response->assertJsonCount(
        0,
        'data.data'
    );
});

test('unauthenticated user cannot list conversations', function () {
    $this
        ->getJson('/api/conversations')
        ->assertUnauthorized();
});

test('business user can see conversations from multiple active businesses they belong to', function () {
    $data = createConversationIndexData();

    $secondBusiness = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
    ]);

    $secondUserPatient = User::factory()->create();

    $secondPatientProfile = PatientProfile::create([
        'user_id' => $secondUserPatient->id,
        'first_name' => 'İkinci',
        'last_name' => 'Hasta',
        'phone' => '05554444444',
    ]);

    $secondConversation = createIndexConversation(
        $data,
        $secondBusiness->id,
        $secondPatientProfile->id,
        null,
        'İkinci business görüşmesi'
    );

    BusinessUser::create([
        'business_id' => $secondBusiness->id,
        'user_id' => $data['businessUser']->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $firstConversation = createIndexConversation(
        $data,
        $data['business']->id,
        $data['patientProfile']->id,
        $data['branch']->id,
        'Birinci business görüşmesi'
    );

    $response = $this
        ->actingAs($data['businessUser'])
        ->getJson('/api/conversations');

    $response->assertOk();

    $response->assertJsonCount(
        2,
        'data.data'
    );

    $response->assertJsonFragment([
        'id' => $firstConversation->id,
    ]);

    $response->assertJsonFragment([
        'id' => $secondConversation->id,
    ]);
});