<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Doctor;
use App\Models\DoctorLeave;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->otherBusiness = Business::factory()->create();

    $this->owner = User::factory()->create();
    $this->staff = User::factory()->create();

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

test('business owner can list doctor leaves', function () {
    DoctorLeave::create([
        'doctor_id' => $this->doctor->id,
        'branch_id' => $this->branch->id,
        'start_date' => '2026-10-10',
        'end_date' => '2026-10-12',
        'leave_type' => 'leave',
        'reason' => 'Yillik izin',
        'is_approved' => true,
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson(
            "/api/branches/{$this->branch->id}/doctors/{$this->doctor->id}/leaves"
        );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.leaves')
        ->assertJsonPath('data.leaves.0.leave_type', 'leave');
});

test('staff can list doctor leaves for assigned branch', function () {
    DoctorLeave::create([
        'doctor_id' => $this->doctor->id,
        'branch_id' => $this->branch->id,
        'start_date' => '2026-10-15',
        'end_date' => '2026-10-16',
        'leave_type' => 'medical',
        'is_approved' => true,
    ]);

    $response = $this->actingAs($this->staff)
        ->getJson(
            "/api/branches/{$this->branch->id}/doctors/{$this->doctor->id}/leaves"
        );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.leaves');
});

test('business owner can create doctor leave', function () {
    $response = $this->actingAs($this->owner)
        ->postJson(
            "/api/branches/{$this->branch->id}/doctors/{$this->doctor->id}/leaves",
            [
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-05',
                'leave_type' => 'leave',
                'reason' => 'Yillik izin',
                'is_approved' => true,
            ]
        );

    $response
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.doctor_id', $this->doctor->id)
        ->assertJsonPath('data.branch_id', $this->branch->id)
        ->assertJsonPath('data.leave_type', 'leave')
        ->assertJsonPath('data.is_approved', true);

    $this->assertDatabaseHas('doctor_leaves', [
        'doctor_id' => $this->doctor->id,
        'branch_id' => $this->branch->id,
        'start_date' => '2026-11-01 00:00:00',
        'end_date' => '2026-11-05 00:00:00',
        'leave_type' => 'leave',
        'is_approved' => true,
    ]);
});

test('staff can create doctor leave for assigned branch', function () {
    $response = $this->actingAs($this->staff)
        ->postJson(
            "/api/branches/{$this->branch->id}/doctors/{$this->doctor->id}/leaves",
            [
                'start_date' => '2026-11-10',
                'end_date' => '2026-11-12',
            ]
        );

    $response
        ->assertCreated()
        ->assertJsonPath('success', true);
});

test('end date before start date is rejected', function () {
    $response = $this->actingAs($this->owner)
        ->postJson(
            "/api/branches/{$this->branch->id}/doctors/{$this->doctor->id}/leaves",
            [
                'start_date' => '2026-12-10',
                'end_date' => '2026-12-05',
            ]
        );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'end_date',
        ]);
});

test('overlapping approved doctor leave is rejected', function () {
    DoctorLeave::create([
        'doctor_id' => $this->doctor->id,
        'branch_id' => $this->branch->id,
        'start_date' => '2026-11-20',
        'end_date' => '2026-11-25',
        'leave_type' => 'leave',
        'is_approved' => true,
    ]);

    $response = $this->actingAs($this->owner)
        ->postJson(
            "/api/branches/{$this->branch->id}/doctors/{$this->doctor->id}/leaves",
            [
                'start_date' => '2026-11-23',
                'end_date' => '2026-11-28',
            ]
        );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'start_date',
        ]);
});

test('non overlapping doctor leave is allowed', function () {
    DoctorLeave::create([
        'doctor_id' => $this->doctor->id,
        'branch_id' => $this->branch->id,
        'start_date' => '2026-11-20',
        'end_date' => '2026-11-25',
        'leave_type' => 'leave',
        'is_approved' => true,
    ]);

    $response = $this->actingAs($this->owner)
        ->postJson(
            "/api/branches/{$this->branch->id}/doctors/{$this->doctor->id}/leaves",
            [
                'start_date' => '2026-11-26',
                'end_date' => '2026-11-30',
            ]
        );

    $response->assertCreated();
});

test('inactive leave does not block a new leave', function () {
    DoctorLeave::create([
        'doctor_id' => $this->doctor->id,
        'branch_id' => $this->branch->id,
        'start_date' => '2026-12-01',
        'end_date' => '2026-12-05',
        'leave_type' => 'leave',
        'is_approved' => false,
    ]);

    $response = $this->actingAs($this->owner)
        ->postJson(
            "/api/branches/{$this->branch->id}/doctors/{$this->doctor->id}/leaves",
            [
                'start_date' => '2026-12-01',
                'end_date' => '2026-12-05',
            ]
        );

    $response->assertCreated();
});

test('business owner can update doctor leave', function () {
    $leave = DoctorLeave::create([
        'doctor_id' => $this->doctor->id,
        'branch_id' => $this->branch->id,
        'start_date' => '2026-12-10',
        'end_date' => '2026-12-12',
        'leave_type' => 'leave',
        'reason' => 'Eski izin',
        'is_approved' => true,
    ]);

    $response = $this->actingAs($this->owner)
        ->putJson(
            "/api/branches/{$this->branch->id}/doctors/{$this->doctor->id}/leaves/{$leave->id}",
            [
                'start_date' => '2026-12-15',
                'end_date' => '2026-12-18',
                'reason' => 'Guncellenen izin',
            ]
        );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.reason', 'Guncellenen izin');

    $this->assertDatabaseHas('doctor_leaves', [
        'id' => $leave->id,
        'start_date' => '2026-12-15 00:00:00',
        'end_date' => '2026-12-18 00:00:00',
        'reason' => 'Guncellenen izin',
    ]);
});

test('business owner can deactivate doctor leave', function () {
    $leave = DoctorLeave::create([
        'doctor_id' => $this->doctor->id,
        'branch_id' => $this->branch->id,
        'start_date' => '2026-12-20',
        'end_date' => '2026-12-22',
        'leave_type' => 'leave',
        'is_approved' => true,
    ]);

    $response = $this->actingAs($this->owner)
        ->deleteJson(
            "/api/branches/{$this->branch->id}/doctors/{$this->doctor->id}/leaves/{$leave->id}"
        );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.is_approved', false);

    $this->assertDatabaseHas('doctor_leaves', [
        'id' => $leave->id,
        'is_approved' => false,
    ]);
});

test('user from another business cannot access doctor leaves', function () {
    $otherUser = User::factory()->create();

    $response = $this->actingAs($otherUser)
        ->getJson(
            "/api/branches/{$this->branch->id}/doctors/{$this->doctor->id}/leaves"
        );

    $response->assertForbidden();
});

test('staff cannot access an unassigned branch doctor leave', function () {
    $response = $this->actingAs($this->staff)
        ->getJson(
            "/api/branches/{$this->otherBranch->id}/doctors/{$this->doctor->id}/leaves"
        );

    $response->assertForbidden();
});

test('doctor from another branch cannot receive a leave', function () {
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
            "/api/branches/{$this->branch->id}/doctors/{$otherDoctor->id}/leaves",
            [
                'start_date' => '2027-01-01',
                'end_date' => '2027-01-03',
            ]
        );

    $response->assertUnprocessable();
});

test('inactive doctor branch relationship cannot receive a leave', function () {
    $this->doctor->branches()->updateExistingPivot(
        $this->branch->id,
        [
            'status' => 'inactive',
        ]
    );

    $response = $this->actingAs($this->owner)
        ->postJson(
            "/api/branches/{$this->branch->id}/doctors/{$this->doctor->id}/leaves",
            [
                'start_date' => '2027-01-10',
                'end_date' => '2027-01-12',
            ]
        );

    $response->assertUnprocessable();
});