<?php

use App\Models\Branch;
use App\Models\BranchLedgerEntry;
use App\Models\CalendarBlock;
use App\Models\CrmLead;
use App\Models\CrmTask;
use App\Models\Doctor;
use App\Models\DoctorLeave;
use App\Models\DoctorWorkingHour;
use App\Models\Person;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the management overview combines authorized branches and calculates conversion and appointment capacity from records', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-30 21:15:00', 'UTC'));
    $branch = Branch::factory()->create(['name' => 'A Merkez']);
    $second = Branch::factory()->for($branch->business)->create(['name' => 'B Şubesi']);
    $foreign = Branch::factory()->create();
    $owner = User::factory()->create();
    $branch->business->memberships()->create(['user_id' => $owner->id, 'role' => 'business_owner', 'is_active' => true]);
    $doctor = Doctor::factory()->for(Person::factory()->for($branch->business)->for($branch))->create();
    $doctor->branches()->attach($branch, ['status' => 'active', 'start_date' => '2026-12-04', 'end_date' => '2026-12-11']);
    foreach ([['09:00', '12:00'], ['11:00', '13:00']] as [$from, $to]) {
        DoctorWorkingHour::create(['doctor_id' => $doctor->id, 'branch_id' => $branch->id, 'day_of_week' => 5,
            'start_time' => $from, 'end_time' => $to, 'is_active' => true]);
    }
    CalendarBlock::create(['business_id' => $branch->business_id, 'branch_id' => $branch->id,
        'starts_at' => '2026-12-04 10:00:00', 'ends_at' => '2026-12-04 11:00:00', 'block_type' => 'manual', 'is_active' => true]);
    DoctorLeave::create(['doctor_id' => $doctor->id, 'branch_id' => $branch->id, 'start_date' => '2026-12-11',
        'end_date' => '2026-12-11', 'is_approved' => true]);
    $patient = User::factory()->create()->patientProfile()->create(['first_name' => 'Prova', 'last_name' => 'Hasta']);
    $lead = CrmLead::create(['business_id' => $branch->business_id, 'branch_id' => $branch->id, 'patient_profile_id' => $patient->id, 'first_name' => 'Aday']);
    CrmLead::create(['business_id' => $branch->business_id, 'branch_id' => $branch->id, 'first_name' => 'Bağlantısız']);
    $conversation = $patient->conversations()->create(['business_id' => $branch->business_id, 'branch_id' => $branch->id, 'crm_lead_id' => $lead->id]);
    foreach (['accepted', 'pending'] as $status) {
        $conversation->offers()->create(['business_id' => $branch->business_id, 'branch_id' => $branch->id,
            'patient_profile_id' => $patient->id, 'created_by' => $owner->id, 'title' => 'Örnek', 'status' => $status, 'amount' => 999]);
    }
    foreach ([
        ['completed', '09:00', '09:30'], ['confirmed', '09:15', '10:15'],
        ['no_show', '11:00', '11:30'], ['cancelled', '11:15', '11:45'], ['rescheduled', '12:00', '12:30'],
    ] as [$status, $from, $to]) {
        $branch->appointments()->create(['business_id' => $branch->business_id, 'doctor_id' => $doctor->id, 'patient_profile_id' => $patient->id,
            'starts_at' => "2026-12-04 {$from}:00", 'ends_at' => "2026-12-04 {$to}:00", 'status' => $status, 'patient_email' => 'private@example.test']);
    }
    CrmTask::create(['business_id' => $branch->business_id, 'branch_id' => $branch->id, 'title' => 'Açık görev']);
    BranchLedgerEntry::factory()->for($branch)->create(['entry_date' => '2026-12-31', 'amount_minor' => 7500]);
    BranchLedgerEntry::factory()->for($second)->create(['entry_date' => '2026-12-01', 'kind' => 'expense', 'amount_minor' => 3000]);
    BranchLedgerEntry::factory()->for($foreign)->create(['entry_date' => '2026-12-01', 'amount_minor' => 99900]);
    $url = '/api/businesses/'.$branch->business_id.'/crm/management?month=2026-12';

    $this->getJson($url)->assertUnauthorized();
    $this->actingAs($owner, 'sanctum')->getJson($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonCount(2, 'data.branches')->assertJsonPath('data.branches.0.lead_count', 2)
        ->assertJsonPath('data.branches.0.offered_count', 1)->assertJsonPath('data.branches.0.booked_count', 1)
        ->assertJsonPath('data.branches.0.open_tasks', 1)->assertJsonPath('data.branches.0.cash.0.income', '75.00')
        ->assertJsonPath('data.branches.1.cash.0.expense', '30.00')->assertJsonPath('data.tasks.data.0.title', 'Açık görev')
        ->assertJsonPath('data.summary.cash.0.net', '45.00')
        ->assertJsonCount(4, 'data.appointments.data')->assertJsonPath('data.performance.cancellation_percent', 25)
        ->assertJsonPath('data.performance.no_show_percent', 50)->assertJsonPath('data.doctors.0.available_minutes', 180)
        ->assertJsonPath('data.doctors.0.booked_minutes', 60)->assertJsonPath('data.doctors.0.occupancy_percent', 33.3)
        ->assertDontSee('private@example.test')->assertDontSee('999.00');

    $staff = User::factory()->create();
    $branch->business->memberships()->create(['user_id' => $staff->id, 'role' => 'staff', 'is_active' => true])->branches()->attach($branch);
    $this->actingAs($staff, 'sanctum')->getJson($url)->assertOk()
        ->assertJsonCount(1, 'data.branches')->assertJsonPath('data.branches.0.cash', null);
    $this->getJson('/api/businesses/'.$foreign->business_id.'/crm/management')->assertForbidden();
});
