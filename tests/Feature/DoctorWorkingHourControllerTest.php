<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Doctor;
use App\Models\DoctorWorkingHour;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->otherBusiness = Business::factory()->create();

    $this->owner = User::factory()->create();
    $this->staff = User::factory()->create();
    $this->unauthorizedStaff = User::factory()->create();

    $this->ownerMembership = BusinessUser::create([
        'business_id' => $this->business->id,
        'user_id' => $this->owner->id,
        'role' => 'business_owner',
        'is_active' => true,
    ]);

    $this->staffMembership = BusinessUser::create([
        'business_id' => $this->business->id,
        'user_id' => $this->staff->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $this->unauthorizedStaffMembership = BusinessUser::create([
        'business_id' => $this->business->id,
        'user_id' => $this->unauthorizedStaff->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $this->branch = Branch::factory()->create([
        'business_id' => $this->business->id,
        'name' => 'Ana Sube',
        'slug' => 'ana-sube',
        'status' => 'active',
    ]);

    $this->otherBranch = Branch::factory()->create([
        'business_id' => $this->business->id,
        'name' => 'Ikinci Sube',
        'slug' => 'ikinci-sube',
        'status' => 'active',
    ]);

    $this->otherBusinessBranch = Branch::factory()->create([
        'business_id' => $this->otherBusiness->id,
        'name' => 'Baska Isletme Subesi',
        'slug' => 'baska-isletme-subesi',
        'status' => 'active',
    ]);

    $this->staffMembership->branches()->attach(
        $this->branch->id,
        [
            'is_active' => true,
        ]
    );

    $this->person = Person::factory()->create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
    ]);

    $this->doctor = Doctor::factory()->create([
        'person_id' => $this->person->id,
        'status' => 'active',
    ]);

    $this->doctor->branches()->attach(
        $this->branch->id,
        [
            'status' => 'active',
        ]
    );
});

test('business owner can list doctor working hours', function () {
    DoctorWorkingHour::create([
        'doctor_id' => $this->doctor->id,
        'branch_id' => $this->branch->id,
        'day_of_week' => 1,
        'start_time' => '09:00',
        'end_time' => '17:00',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson(
            "/api/branches/{$this->branch->id}/doctors/{$this->doctor->id}/working-hours"
        );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.working_hours')
        ->assertJsonPath('data.working_hours.0.day_of_week', 1);
});

test('staff can list doctor working hours for assigned branch', function () {
    DoctorWorkingHour::create([
        'doctor_id' => $this->doctor->id,
        'branch_id' => $this->branch->id,
        'day_of_week' => 2,
        'start_time' => '10:00',
        'end_time' => '18:00',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->staff)
        ->getJson(
            "/api/branches/{$this->branch->id}/doctors/{$this->doctor->id}/working-hours"
        );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.working_hours');
});

test('business owner can create doctor working hour', function () {
    $response = $this->actingAs($this->owner)
        ->postJson(
            "/api/branches/{$this->branch->id}/doctors/{$this->doctor->id}/working-hours",
            [
                'day_of_week' => 1,
                'start_time' => '09:00',
                'end_time' => '13:00',
                'is_active' => true,
            ]
        );

    $response
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.day_of_week', 1)
        ->assertJsonPath('data.start_time', '09:00')
        ->assertJsonPath('data.end_time', '13:00');

    $this->assertDatabaseHas('doctor_working_hours', [
        'doctor_id' => $this->doctor->id,
        'branch_id' => $this->branch->id,
        'day_of_week' => 1,
        'start_time' => '09:00',
        'end_time' => '13:00',
        'is_active' => true,
    ]);
});

test('staff can create doctor working hour for assigned branch', function () {
    $response = $this->actingAs($this->staff)
        ->postJson(
            "/api/branches/{$this->branch->id}/doctors/{$this->doctor->id}/working-hours",
            [
                'day_of_week' => 2,
                'start_time' => '10:00',
                'end_time' => '14:00',
            ]
        );

    $response
        ->assertCreated()
        ->assertJsonPath('success', true);
});

test('invalid time range is rejected', function () {
    $response = $this->actingAs($this->owner)
        ->postJson(
            "/api/branches/{$this->branch->id}/doctors/{$this->doctor->id}/working-hours",
            [
                'day_of_week' => 1,
                'start_time' => '17:00',
                'end_time' => '09:00',
            ]
        );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'end_time',
        ]);
});

test('same start and end time is rejected', function () {
    $response = $this->actingAs($this->owner)
        ->postJson(
            "/api/branches/{$this->branch->id}/doctors/{$this->doctor->id}/working-hours",
            [
                'day_of_week' => 1,
                'start_time' => '09:00',
                'end_time' => '09:00',
            ]
        );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'end_time',
        ]);
});

test('overlapping working hours are rejected', function () {
    DoctorWorkingHour::create([
        'doctor_id' => $this->doctor->id,
        'branch_id' => $this->branch->id,
        'day_of_week' => 1,
        'start_time' => '09:00',
        'end_time' => '13:00',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->owner)
        ->postJson(
            "/api/branches/{$this->branch->id}/doctors/{$this->doctor->id}/working-hours",
            [
                'day_of_week' => 1,
                'start_time' => '12:00',
                'end_time' => '15:00',
            ]
        );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'start_time',
        ]);
});

test('non-overlapping working hours on same day are allowed', function () {
    DoctorWorkingHour::create([
        'doctor_id' => $this->doctor->id,
        'branch_id' => $this->branch->id,
        'day_of_week' => 1,
        'start_time' => '09:00',
        'end_time' => '13:00',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->owner)
        ->postJson(
            "/api/branches/{$this->branch->id}/doctors/{$this->doctor->id}/working-hours",
            [
                'day_of_week' => 1,
                'start_time' => '14:00',
                'end_time' => '18:00',
            ]
        );

    $response->assertCreated();
});

test('business owner can update doctor working hour', function () {
    $workingHour = DoctorWorkingHour::create([
        'doctor_id' => $this->doctor->id,
        'branch_id' => $this->branch->id,
        'day_of_week' => 1,
        'start_time' => '09:00',
        'end_time' => '13:00',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->owner)
        ->putJson(
            "/api/doctor-working-hours/{$workingHour->id}",
            [
                'start_time' => '10:00',
                'end_time' => '14:00',
            ]
        );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.start_time', '10:00')
        ->assertJsonPath('data.end_time', '14:00');
});

test('business owner can toggle doctor working hour', function () {
    $workingHour = DoctorWorkingHour::create([
        'doctor_id' => $this->doctor->id,
        'branch_id' => $this->branch->id,
        'day_of_week' => 1,
        'start_time' => '09:00',
        'end_time' => '13:00',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->owner)
        ->patchJson(
            "/api/doctor-working-hours/{$workingHour->id}/toggle"
        );

    $response
        ->assertSuccessful()
        ->assertJsonPath('data.is_active', false);

    $this->assertDatabaseHas('doctor_working_hours', [
        'id' => $workingHour->id,
        'is_active' => false,
    ]);
});

test('business owner can delete doctor working hour', function () {
    $workingHour = DoctorWorkingHour::create([
        'doctor_id' => $this->doctor->id,
        'branch_id' => $this->branch->id,
        'day_of_week' => 1,
        'start_time' => '09:00',
        'end_time' => '13:00',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->owner)
        ->deleteJson(
            "/api/doctor-working-hours/{$workingHour->id}"
        );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('doctor_working_hours', [
        'id' => $workingHour->id,
    ]);
});

test('user from another business cannot access doctor working hours', function () {
    $otherUser = User::factory()->create();

    $response = $this->actingAs($otherUser)
        ->getJson(
            "/api/branches/{$this->branch->id}/doctors/{$this->doctor->id}/working-hours"
        );

    $response->assertForbidden();
});

test('staff cannot access an unassigned branch', function () {
    $response = $this->actingAs($this->staff)
        ->getJson(
            "/api/branches/{$this->otherBranch->id}/doctors/{$this->doctor->id}/working-hours"
        );

    $response->assertForbidden();
});

test('doctor from another branch cannot be used for working hours', function () {
    $otherPerson = Person::factory()->create([
        'business_id' => $this->business->id,
        'branch_id' => $this->otherBranch->id,
    ]);

    $otherDoctor = Doctor::factory()->create([
        'person_id' => $otherPerson->id,
        'status' => 'active',
    ]);

    $otherDoctor->branches()->attach(
        $this->otherBranch->id,
        [
            'status' => 'active',
        ]
    );

    $response = $this->actingAs($this->owner)
        ->postJson(
            "/api/branches/{$this->branch->id}/doctors/{$otherDoctor->id}/working-hours",
            [
                'day_of_week' => 1,
                'start_time' => '09:00',
                'end_time' => '13:00',
            ]
        );

    $response->assertUnprocessable();
});

test('inactive doctor branch relationship cannot be used', function () {
    $this->doctor->branches()->updateExistingPivot(
        $this->branch->id,
        [
            'status' => 'inactive',
        ]
    );

    $response = $this->actingAs($this->owner)
        ->postJson(
            "/api/branches/{$this->branch->id}/doctors/{$this->doctor->id}/working-hours",
            [
                'day_of_week' => 1,
                'start_time' => '09:00',
                'end_time' => '13:00',
            ]
        );

    $response->assertUnprocessable();
});

test('inactive working hour can be reactivated when there is no conflict', function () {
    $workingHour = DoctorWorkingHour::create([
        'doctor_id' => $this->doctor->id,
        'branch_id' => $this->branch->id,
        'day_of_week' => 1,
        'start_time' => '09:00',
        'end_time' => '13:00',
        'is_active' => false,
    ]);

    $response = $this->actingAs($this->owner)
        ->patchJson(
            "/api/doctor-working-hours/{$workingHour->id}/toggle"
        );

    $response
        ->assertSuccessful()
        ->assertJsonPath('data.is_active', true);
});