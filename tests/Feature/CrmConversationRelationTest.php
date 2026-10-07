<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\CrmLead;
use App\Models\PatientProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('crm lead can access its conversations', function () {
    $business = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
    ]);

    $branch = Branch::create([
        'business_id' => $business->id,
        'name' => 'Test Şubesi',
        'slug' => 'test-subesi-'.uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
    ]);

    $patientUser = \App\Models\User::factory()->create();

$patientProfile = PatientProfile::create([
    'user_id' => $patientUser->id,
    'first_name' => 'Test',
    'last_name' => 'Hasta',
    'phone' => '05550000000',
]);

    $lead = CrmLead::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'patient_profile_id' => $patientProfile->id,
        'first_name' => 'Test',
        'last_name' => 'Lead',
        'source' => 'manual',
        'status' => 'new',
        'priority' => 'normal',
    ]);

    $conversation = Conversation::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'patient_profile_id' => $patientProfile->id,
        'crm_lead_id' => $lead->id,
        'subject' => 'CRM görüşmesi',
        'status' => 'open',
    ]);

    $lead->load('conversations');

    expect($lead->conversations)
        ->toHaveCount(1)
        ->and($lead->conversations->first()->id)
        ->toBe($conversation->id);
});

test('conversation can access its crm lead', function () {
    $business = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
    ]);

    $branch = Branch::create([
        'business_id' => $business->id,
        'name' => 'Test Şubesi',
        'slug' => 'test-subesi-'.uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
    ]);

    $patientUser = \App\Models\User::factory()->create();

$patientProfile = PatientProfile::create([
    'user_id' => $patientUser->id,
    'first_name' => 'Test',
    'last_name' => 'Hasta',
    'phone' => '05550000000',
]);

    $lead = CrmLead::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'patient_profile_id' => $patientProfile->id,
        'first_name' => 'Test',
        'last_name' => 'Lead',
        'source' => 'manual',
        'status' => 'new',
        'priority' => 'normal',
    ]);

    $conversation = Conversation::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'patient_profile_id' => $patientProfile->id,
        'crm_lead_id' => $lead->id,
        'subject' => 'CRM görüşmesi',
        'status' => 'open',
    ]);

    $conversation->load('crmLead');

    expect($conversation->crmLead)
        ->not->toBeNull()
        ->and($conversation->crmLead->id)
        ->toBe($lead->id);
});

test('conversation can exist without crm lead', function () {
    $business = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
    ]);

    $branch = Branch::create([
        'business_id' => $business->id,
        'name' => 'Test Şubesi',
        'slug' => 'test-subesi-'.uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
    ]);

    $patientUser = \App\Models\User::factory()->create();

$patientProfile = PatientProfile::create([
    'user_id' => $patientUser->id,
    'first_name' => 'Test',
    'last_name' => 'Hasta',
    'phone' => '05550000000',
]);

    $conversation = Conversation::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'patient_profile_id' => $patientProfile->id,
        'subject' => 'Normal hasta görüşmesi',
        'status' => 'open',
    ]);

    $conversation->load('crmLead');

    expect($conversation->crmLead)->toBeNull();
});