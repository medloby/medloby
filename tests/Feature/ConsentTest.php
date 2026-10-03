<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Consent;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\PatientProfile;
use App\Models\Person;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('patient can have a consent connected to medical context', function () {
    $user = User::factory()->create();

    $business = Business::factory()->create();

    BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'is_active' => true,
    ]);

    $patient = PatientProfile::create([
        'user_id' => $user->id,
        'first_name' => 'Test',
        'last_name' => 'Hasta',
        'phone' => '05550000000',
        'preferred_language' => 'tr',
        'preferred_currency' => 'TRY',
        'status' => 'active',
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

    $person = Person::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'first_name' => 'Test',
        'last_name' => 'Doktor',
        'title' => 'Dr.',
        'job_title' => 'Doktor',
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
    ]);

    $doctor = Doctor::create([
        'person_id' => $person->id,
        'license_number' => 'TEST-' . uniqid(),
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
        'is_public' => true,
    ]);

    $category = TreatmentCategory::create([
        'name' => 'Test Kategorisi',
        'slug' => 'test-kategorisi-' . uniqid(),
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $treatment = Treatment::create([
        'treatment_category_id' => $category->id,
        'name' => 'Test Tedavisi',
        'slug' => 'test-tedavisi-' . uniqid(),
        'duration_minutes' => 60,
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $medicalRecord = MedicalRecord::create([
        'patient_profile_id' => $patient->id,
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'doctor_id' => $doctor->id,
        'created_by_user_id' => $user->id,
        'record_type' => 'clinical_note',
        'title' => 'İlk Muayene',
        'status' => 'active',
        'recorded_at' => now(),
    ]);

    $consent = Consent::create([
        'patient_profile_id' => $patient->id,
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'treatment_id' => $treatment->id,
        'medical_record_id' => $medicalRecord->id,
        'created_by_user_id' => $user->id,
        'consent_type' => 'treatment',
        'title' => 'Tedavi Onam Formu',
        'content' => 'Test onam metni.',
        'version' => '1.0',
        'status' => 'pending',
        'requested_at' => now(),
    ]);

    expect($consent->exists)->toBeTrue();

    $this->assertDatabaseHas('consents', [
        'id' => $consent->id,
        'patient_profile_id' => $patient->id,
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'treatment_id' => $treatment->id,
        'medical_record_id' => $medicalRecord->id,
        'created_by_user_id' => $user->id,
        'consent_type' => 'treatment',
        'version' => '1.0',
        'status' => 'pending',
    ]);

    $consent->load([
        'patientProfile',
        'business',
        'branch',
        'treatment',
        'medicalRecord',
        'createdBy',
    ]);

    expect($consent->patientProfile->id)->toBe($patient->id);
    expect($consent->business->id)->toBe($business->id);
    expect($consent->branch->id)->toBe($branch->id);
    expect($consent->treatment->id)->toBe($treatment->id);
    expect($consent->medicalRecord->id)->toBe($medicalRecord->id);
    expect($consent->createdBy->id)->toBe($user->id);
});

test('patient profile can access its consents', function () {
    $user = User::factory()->create();

    $business = Business::factory()->create();

    $patient = PatientProfile::create([
        'user_id' => $user->id,
        'first_name' => 'Test',
        'last_name' => 'Hasta',
        'preferred_language' => 'tr',
        'preferred_currency' => 'TRY',
        'status' => 'active',
    ]);

    $consent = Consent::create([
        'patient_profile_id' => $patient->id,
        'business_id' => $business->id,
        'consent_type' => 'privacy',
        'title' => 'Gizlilik Onamı',
        'content' => 'Test gizlilik metni.',
        'version' => '1.0',
        'status' => 'pending',
        'requested_at' => now(),
    ]);

    $patient->load('consents');

    expect($patient->consents)->toHaveCount(1);
    expect($patient->consents->first()->id)->toBe($consent->id);
    expect($patient->consents->first()->consent_type)->toBe('privacy');
    expect($patient->consents->first()->version)->toBe('1.0');
});