<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\PatientProfile;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('patient can have a medical record connected to business branch and doctor', function () {
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

    $record = MedicalRecord::create([
        'patient_profile_id' => $patient->id,
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'doctor_id' => $doctor->id,
        'created_by_user_id' => $user->id,
        'record_type' => 'clinical_note',
        'title' => 'İlk Muayene',
        'complaint' => 'Test şikayeti',
        'examination' => 'Test muayene bulguları',
        'diagnosis' => 'Test değerlendirmesi',
        'treatment' => 'Test tedavi planı',
        'notes' => 'Test tıbbi kayıt notu',
        'status' => 'active',
        'recorded_at' => now(),
    ]);

    expect($record->exists)->toBeTrue();

    $this->assertDatabaseHas('medical_records', [
        'id' => $record->id,
        'patient_profile_id' => $patient->id,
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'doctor_id' => $doctor->id,
        'created_by_user_id' => $user->id,
        'title' => 'İlk Muayene',
        'status' => 'active',
    ]);

    $record->load([
        'patientProfile',
        'business',
        'branch',
        'doctor',
        'createdBy',
    ]);

    expect($record->patientProfile->id)->toBe($patient->id);
    expect($record->business->id)->toBe($business->id);
    expect($record->branch->id)->toBe($branch->id);
    expect($record->doctor->id)->toBe($doctor->id);
    expect($record->createdBy->id)->toBe($user->id);
});

test('patient profile can access its medical records', function () {
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

    $record = MedicalRecord::create([
        'patient_profile_id' => $patient->id,
        'business_id' => $business->id,
        'record_type' => 'clinical_note',
        'title' => 'Hasta Kayıt Testi',
        'status' => 'active',
        'recorded_at' => now(),
    ]);

    $patient->load('medicalRecords');

    expect($patient->medicalRecords)->toHaveCount(1);
    expect($patient->medicalRecords->first()->id)->toBe($record->id);
});