<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Business;
use App\Models\CalendarBlock;
use App\Models\Doctor;
use App\Models\Person;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\User;
use App\Services\AppointmentAvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->branch = Branch::factory()->for($this->business)->create();
    $this->otherBranch = Branch::factory()->for($this->business)->create();
    $this->owner = User::factory()->create();
    $this->business->memberships()->create([
        'user_id' => $this->owner->id, 'role' => 'business_owner', 'is_active' => true,
    ]);
    $person = Person::factory()->for($this->business)->for($this->branch)->create([
        'first_name' => 'Deneme', 'last_name' => 'Hekim',
    ]);
    $this->doctor = Doctor::factory()->for($person)->create(['status' => 'active']);
    $this->doctor->branches()->attach($this->branch->id, ['status' => 'active']);
    $this->calendar = "/api/branches/{$this->branch->id}/clinic-calendar";
    $this->day = $this->calendar.'?date=2026-12-04';
    $this->otherCalendar = "/api/branches/{$this->otherBranch->id}/clinic-calendar";
});

test('the agenda shows only overlapping appointments and active blocks in the assigned branch', function () {
    $staff = User::factory()->create();
    $membership = $this->business->memberships()->create([
        'user_id' => $staff->id, 'role' => 'staff', 'is_active' => true,
    ]);
    $membership->branches()->attach($this->branch->id, ['is_active' => true]);

    foreach ([
        [$this->branch->id, 'Selected branch patient', '2026-12-04 10:00:00', '2026-12-04 11:00:00'],
        [$this->branch->id, 'Overnight patient', '2026-12-03 23:30:00', '2026-12-04 00:15:00'],
        [$this->branch->id, 'Previous day patient', '2026-12-03 23:00:00', '2026-12-04 00:00:00'],
        [$this->otherBranch->id, 'Other branch patient', '2026-12-04 10:00:00', '2026-12-04 11:00:00'],
    ] as [$branchId, $patient, $start, $end]) {
        Appointment::create([
            'business_id' => $this->business->id, 'branch_id' => $branchId, 'doctor_id' => $this->doctor->id,
            'patient_name' => $patient, 'patient_email' => 'private@example.test',
            'starts_at' => $start, 'ends_at' => $end, 'status' => 'confirmed',
        ]);
    }
    $block = [
        'business_id' => $this->business->id, 'branch_id' => $this->branch->id, 'doctor_id' => $this->doctor->id,
        'starts_at' => '2026-12-04 12:00:00', 'ends_at' => '2026-12-04 13:00:00', 'block_type' => 'manual',
        'title' => 'Lunch break', 'reason' => 'Private block reason',
    ];
    CalendarBlock::create([...$block, 'is_active' => true]);
    CalendarBlock::create([...$block, 'is_active' => false]);

    $this->getJson($this->day)->assertUnauthorized();
    $this->actingAs($staff, 'sanctum')->getJson($this->day)
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonCount(2, 'data.appointments')
        ->assertJsonPath('data.appointments.0.patient', 'Overnight patient')
        ->assertJsonPath('data.appointments.1.patient', 'Selected branch patient')
        ->assertJsonPath('data.appointments.1.doctor', 'Deneme Hekim')
        ->assertJsonMissingPath('data.appointments.1.patient_email')
        ->assertJsonCount(1, 'data.blocks')
        ->assertJsonPath('data.blocks.0.title', 'Lunch break')
        ->assertJsonPath('data.blocks.0.reason', null)
        ->assertJsonPath('data.doctors', []);
    $this->getJson($this->otherCalendar.'?date=2026-12-04')->assertForbidden();
    $this->getJson($this->calendar.'/rules')->assertForbidden();
    $this->postJson($this->calendar.'/blocks', $block)->assertForbidden();

    $this->actingAs($this->owner, 'sanctum')->getJson($this->day)
        ->assertOk()->assertJsonPath('data.blocks.0.reason', 'Private block reason')
        ->assertJsonPath('data.doctors.0.id', $this->doctor->id);
    $this->getJson($this->calendar.'?date=04-12-2026')->assertUnprocessable()->assertJsonValidationErrors('date');
    $this->business->update(['status' => 'suspended']);
    $this->getJson($this->day)->assertForbidden();
});

test('the clinic owner can manage online booking settings from the branch calendar', function () {
    $path = $this->calendar.'/appointment-settings';
    $settings = [
        'online_booking_enabled' => false, 'same_day_booking_enabled' => false,
        'minimum_booking_notice_minutes' => 60, 'maximum_booking_days' => 14, 'is_active' => true,
    ];
    $this->getJson($path)->assertUnauthorized();
    $this->actingAs($this->owner, 'sanctum')->getJson($path)->assertOk()
        ->assertJsonPath('data.settings.online_booking_enabled', true)
        ->assertJsonPath('data.settings.maximum_booking_days', 90);
    $this->assertDatabaseCount('branch_appointment_settings', 0);
    $this->putJson($path, [...$settings, 'maximum_booking_days' => 0])
        ->assertUnprocessable()->assertJsonValidationErrors('maximum_booking_days');
    $this->putJson($path, $settings)->assertOk()->assertJsonPath('data.maximum_booking_days', 14);
    $this->assertDatabaseHas('branch_appointment_settings', ['branch_id' => $this->branch->id, ...$settings]);
    $this->getJson($this->day)->assertOk()->assertJsonPath('data.online_booking_enabled', false);
    $this->getJson($this->otherCalendar.'/appointment-settings')->assertOk()
        ->assertJsonPath('data.settings.maximum_booking_days', 90);

    $this->branch->appointmentSetting()->update(['is_active' => false, 'slot_interval_minutes' => 45]);
    $this->getJson($this->day)->assertOk()->assertJsonPath('data.online_booking_enabled', true);
    $this->getJson($path)->assertOk()->assertJsonPath('data.settings.is_active', false);
    $this->putJson($path, [...$settings, 'online_booking_enabled' => true])->assertOk()
        ->assertJsonPath('data.slot_interval_minutes', 45)->assertJsonPath('data.is_active', true);
    $this->assertDatabaseCount('branch_appointment_settings', 1);
    $this->business->update(['status' => 'suspended']);
    $this->putJson($path, $settings)->assertForbidden();
});

test('staff can change online booking settings only while assigned to that branch', function () {
    $staff = User::factory()->create();
    $membership = $this->business->memberships()->create([
        'user_id' => $staff->id, 'role' => 'staff', 'is_active' => true,
    ]);
    $membership->branches()->attach($this->branch->id, ['is_active' => true]);
    $path = $this->calendar.'/appointment-settings';
    $otherPath = $this->otherCalendar.'/appointment-settings';

    $this->actingAs($staff, 'sanctum')->getJson($path)->assertOk()
        ->assertJsonPath('data.settings.maximum_booking_days', 90);
    $this->putJson($path, ['online_booking_enabled' => false, 'maximum_booking_days' => 21])->assertOk()
        ->assertJsonPath('data.maximum_booking_days', 21);
    $this->getJson($this->day)->assertOk()->assertJsonPath('data.online_booking_enabled', false);
    $this->getJson($otherPath)->assertForbidden();
    $this->putJson($otherPath, ['maximum_booking_days' => 30])->assertForbidden();
    $this->putJson($this->calendar.'/rules', ['default_status' => 'closed'])->assertForbidden();
    $this->assertDatabaseMissing('branch_appointment_settings', ['branch_id' => $this->otherBranch->id]);

    $membership->branches()->updateExistingPivot($this->branch->id, ['is_active' => false]);
    $this->getJson($path)->assertForbidden();
    $this->putJson($path, ['maximum_booking_days' => 30])->assertForbidden();
    $this->assertDatabaseHas('branch_appointment_settings', ['branch_id' => $this->branch->id, 'maximum_booking_days' => 21]);
});

test('calendar settings change booking availability and cannot be edited from another branch', function () {
    $treatment = Treatment::create([
        'treatment_category_id' => TreatmentCategory::factory()->create()->id,
        'name' => 'Muayene', 'slug' => 'takvim-muayene', 'is_active' => true, 'duration_minutes' => 30,
    ]);
    $this->branch->treatments()->attach($treatment->id, ['is_active' => true, 'is_online_bookable' => true]);
    $this->doctor->treatments()->attach($treatment->id, ['is_active' => true]);
    $available = fn () => app(AppointmentAvailabilityService::class)->isAvailable(
        $this->doctor, $this->branch->id, $treatment, Carbon::parse('2026-12-04 10:00:00'),
    );
    $dates = ['start_date' => '2026-12-04', 'end_date' => '2026-12-04'];
    $doctorPath = $this->calendar.'/doctors/'.$this->doctor->id;
    $this->actingAs($this->owner, 'sanctum');

    expect($available())->toBeFalse();
    $hour = $this->postJson($doctorPath.'/working-hours', [
        'day_of_week' => 5, 'start_time' => '09:00', 'end_time' => '12:00',
    ])->assertCreated()->json('data.id');
    expect($available())->toBeTrue();
    $this->putJson($this->otherCalendar.'/working-hours/'.$hour, ['is_active' => false])->assertNotFound();
    $this->putJson($this->calendar.'/working-hours/'.$hour, ['is_active' => false])->assertOk();
    expect($available())->toBeFalse();
    $this->putJson($this->calendar.'/working-hours/'.$hour, ['is_active' => true])->assertOk();
    expect($available())->toBeTrue();

    $this->putJson($this->calendar.'/rules', ['default_status' => 'closed'])->assertOk();
    $this->getJson($this->day)->assertOk()->assertJsonPath('data.open', false);
    expect($available())->toBeFalse();
    $override = $this->postJson($this->calendar.'/overrides', [...$dates, 'status' => 'open'])
        ->assertCreated()->json('data.id');
    $this->getJson($this->day)->assertOk()->assertJsonPath('data.open', true);
    expect($available())->toBeTrue();

    $leave = $this->postJson($doctorPath.'/leaves', $dates)
        ->assertCreated()->assertJsonPath('data.branch_id', $this->branch->id)->json('data.id');
    expect($available())->toBeFalse();
    $this->deleteJson($doctorPath.'/leaves/'.$leave)->assertOk();
    expect($available())->toBeTrue();

    $block = $this->postJson($this->calendar.'/blocks', [
        'starts_at' => '2026-12-04 09:45:00', 'ends_at' => '2026-12-04 10:45:00', 'doctor_id' => $this->doctor->id,
    ])->assertCreated()->json('data.id');
    expect($available())->toBeFalse();
    $this->putJson($this->calendar.'/blocks/'.$block, ['title' => 'Meeting', 'doctor_id' => null])->assertOk();
    $this->getJson($this->day)->assertOk()
        ->assertJsonPath('data.blocks.0.title', 'Meeting')
        ->assertJsonPath('data.blocks.0.doctor_id', null);
    expect($available())->toBeFalse();
    $this->deleteJson($this->calendar.'/blocks/'.$block)->assertOk();
    $this->getJson($this->day)->assertOk()->assertJsonCount(0, 'data.blocks');
    expect($available())->toBeTrue();

    $this->deleteJson($this->calendar.'/overrides/'.$override)->assertOk();
    expect($available())->toBeFalse();
});
