<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchLedgerEntry;
use App\Models\Doctor;
use App\Models\Person;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function financeReportClinic(): array
{
    $branch = Branch::factory()->create(['name' => 'A Merkez']);
    $owner = User::factory()->create();
    $branch->business->memberships()->create(['user_id' => $owner->id, 'role' => 'business_owner', 'is_active' => true]);

    return [$branch, $owner, '/api/businesses/'.$branch->business_id.'/crm/finance/reports'];
}

function financeReportVisit(Branch $branch, array $attributes = []): Appointment
{
    return $branch->appointments()->create([
        'business_id' => $branch->business_id,
        'starts_at' => '2026-12-15 09:00:00',
        'ends_at' => '2026-12-15 09:30:00',
        'status' => 'completed',
        'patient_name' => 'Özel Hasta Adı',
        'patient_email' => 'private@example.test',
        ...$attributes,
    ]);
}

test('finance reports reconcile actual receipts by branch, currency and period and count each rescheduled visit once', function () {
    [$branch, $owner, $path] = financeReportClinic();
    $other = Branch::factory()->for($branch->business)->create(['name' => 'B Şubesi']);
    $archived = Branch::factory()->for($branch->business)->create(['status' => 'inactive']);
    $foreign = Branch::factory()->create();
    $doctor = Doctor::factory()->for(Person::factory()->for($branch->business)->for($branch)->state([
        'title' => 'Dr.', 'first_name' => 'Deneme', 'last_name' => 'Hekim',
    ]))->create();
    $treatment = Treatment::create([
        'treatment_category_id' => TreatmentCategory::factory()->create()->id,
        'name' => 'Örnek Muayene', 'slug' => 'ornek-muayene', 'is_active' => true,
    ]);
    $assignment = ['doctor_id' => $doctor->id, 'treatment_id' => $treatment->id];
    $old = financeReportVisit($branch, $assignment + ['status' => 'rescheduled']);
    $new = financeReportVisit($branch, $assignment + ['rescheduled_from_appointment_id' => $old->id]);
    financeReportVisit($branch, $assignment + ['status' => 'no_show']);
    financeReportVisit($branch, $assignment + ['status' => 'cancelled']);
    financeReportVisit($other, $assignment + ['status' => 'confirmed']);
    BranchLedgerEntry::factory()->for($branch)->create(['entry_date' => '2026-12-31', 'amount_minor' => 10010, 'appointment_id' => $old->id]);
    BranchLedgerEntry::factory()->for($branch)->create(['entry_date' => '2026-12-31', 'amount_minor' => 2000]);
    BranchLedgerEntry::factory()->for($branch)->create(['entry_date' => '2026-12-01', 'kind' => 'expense', 'amount_minor' => 505]);
    BranchLedgerEntry::factory()->for($branch)->create(['entry_date' => '2026-12-01', 'amount_minor' => 215, 'currency' => 'EUR', 'appointment_id' => $new->id]);
    BranchLedgerEntry::factory()->for($branch)->create(['entry_date' => '2026-11-30', 'amount_minor' => 8000]);
    BranchLedgerEntry::factory()->for($branch)->create(['entry_date' => '2027-01-01', 'amount_minor' => 3000, 'appointment_id' => $new->id]);
    BranchLedgerEntry::factory()->for($branch)->create(['entry_date' => '2026-12-15', 'voided_at' => now(), 'appointment_id' => $new->id]);
    BranchLedgerEntry::factory()->for($other)->create(['entry_date' => '2026-12-15', 'amount_minor' => 5020]);
    BranchLedgerEntry::factory()->for($archived)->create(['entry_date' => '2026-12-15']);
    BranchLedgerEntry::factory()->for($foreign)->create(['entry_date' => '2026-12-15']);

    $this->actingAs($owner, 'sanctum')->getJson($path.'?period=month&month=2026-12&branch_id='.$branch->id)
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonCount(1, 'data.branches')
        ->assertJsonPath('data.starts_on', '2026-12-01')->assertJsonPath('data.ends_on', '2026-12-31')
        ->assertJsonPath('data.totals', [
            ['currency' => 'EUR', 'income' => '2.15', 'expense' => '0.00', 'net' => '2.15', 'unattributed_income' => '0.00'],
            ['currency' => 'TRY', 'income' => '120.10', 'expense' => '5.05', 'net' => '115.05', 'unattributed_income' => '20.00'],
        ])->assertJsonPath('data.doctors.0', [
            'id' => $doctor->id, 'name' => 'Dr. Deneme Hekim', 'total' => 3, 'completed' => 1,
            'scheduled' => 0, 'cancelled' => 1, 'no_show' => 1,
            'treatments' => [['name' => 'Örnek Muayene', 'total' => 3, 'completed' => 1]],
            'income' => [['currency' => 'EUR', 'amount' => '2.15'], ['currency' => 'TRY', 'amount' => '100.10']],
        ])->assertDontSee('private@example.test')->assertDontSee('Özel Hasta Adı');
    $this->getJson($path.'?period=year&year=2026')->assertOk()
        ->assertJsonCount(2, 'data.branches')->assertJsonCount(2, 'data.months')
        ->assertJsonPath('data.branches.1.totals.0.income', '50.20')->assertJsonPath('data.totals.1.income', '250.30')
        ->assertJsonPath('data.doctors.0.total', 4)->assertJsonPath('data.doctors.0.scheduled', 1)
        ->assertJsonPath('data.months.0.month', '2026-11')->assertJsonPath('data.months.1.month', '2026-12');
    $this->getJson($path.'?period=all')->assertOk()->assertJsonPath('data.totals.1.income', '280.30')
        ->assertJsonPath('data.doctors.0.income.1.amount', '130.10')->assertJsonCount(3, 'data.months');
    $this->getJson($path.'?period=month&month=2027-01')->assertOk()->assertJsonPath('data.doctors.0.total', 0)
        ->assertJsonPath('data.doctors.0.income.0.amount', '30.00');
});

test('business-wide reports use the same active branch finance permissions as the ledger', function () {
    [$branch, , $path] = financeReportClinic();
    $other = Branch::factory()->for($branch->business)->create();
    $staff = User::factory()->create();
    $membership = $branch->business->memberships()->create(['user_id' => $staff->id, 'role' => 'staff', 'is_active' => true]);
    $membership->branches()->attach($branch, ['is_active' => true, 'permission_names' => ['finance.view']]);
    $membership->branches()->attach($other, ['is_active' => true]);
    BranchLedgerEntry::factory()->for($branch)->create(['amount_minor' => 7000]);
    BranchLedgerEntry::factory()->for($other)->create(['amount_minor' => 9000]);

    $this->getJson($path)->assertUnauthorized();
    $this->actingAs(User::factory()->create(['is_admin' => true]), 'sanctum')->getJson($path)->assertForbidden();
    $this->actingAs($staff, 'sanctum')->getJson($path.'?period=all')->assertOk()
        ->assertJsonCount(1, 'data.branches')->assertJsonPath('data.branches.0.id', $branch->id)->assertJsonPath('data.totals.0.income', '70.00');
    $this->getJson($path.'?branch_id='.$other->id)->assertForbidden();
    $this->getJson($path.'?period=year&year=0000')->assertUnprocessable()->assertJsonValidationErrors('year');
    $this->getJson($path.'?period=month&month=2026-13')->assertUnprocessable()->assertJsonValidationErrors('month');
    $membership->branches()->updateExistingPivot($branch, ['is_active' => false]);
    $this->getJson($path)->assertForbidden();
});

test('ledger income accepts one verified reference in the branch and preserves links for old clients', function () {
    [$branch, $owner, $reportPath] = financeReportClinic();
    $visit = financeReportVisit($branch);
    $other = financeReportVisit(Branch::factory()->for($branch->business)->create());
    $patient = User::factory()->create()->patientProfile()->create(['first_name' => 'Özel', 'last_name' => 'Hasta']);
    $conversation = $patient->conversations()->create(['business_id' => $branch->business_id, 'branch_id' => $branch->id]);
    $offer = $conversation->offers()->create([
        'patient_profile_id' => $patient->id, 'business_id' => $branch->business_id, 'created_by' => $owner->id,
        'title' => 'Özel hasta teklifi', 'status' => 'accepted', 'amount' => 1000, 'package_snapshot' => ['branch_id' => $branch->id],
    ]);
    $path = '/api/branches/'.$branch->id.'/crm/ledger';
    $fields = ['kind' => 'income', 'entry_date' => '2026-12-15', 'amount' => '100.00', 'currency' => 'TRY', 'description' => 'Tahsilat'];
    $key = ['request_key' => str_repeat('a', 32)];

    $this->actingAs($owner, 'sanctum')->getJson($path.'/references?type=appointment')->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $visit->id)->assertDontSee('private@example.test');
    $this->getJson($path.'/references?type=package')->assertOk()
        ->assertJsonPath('data.0.id', $offer->id)->assertDontSee('Özel hasta teklifi');
    $this->postJson($path, $fields + $key + ['appointment_id' => $other->id])->assertUnprocessable()->assertJsonValidationErrors('appointment_id');
    $this->postJson($path, $fields + $key + ['appointment_id' => $visit->id, 'offer_id' => $offer->id])
        ->assertUnprocessable()->assertJsonValidationErrors('appointment_id');
    $this->postJson($path, ['kind' => 'expense'] + $fields + $key + ['offer_id' => $offer->id])->assertUnprocessable()->assertJsonValidationErrors('offer_id');
    $entry = $this->postJson($path, $fields + $key + ['appointment_id' => $visit->id])->assertCreated()
        ->assertJsonPath('data.appointment_id', $visit->id)->json('data.id');
    $this->postJson($path, $fields + $key + ['appointment_id' => $visit->id])->assertOk()->assertJsonPath('data.id', $entry);
    $this->putJson($path.'/'.$entry, ['description' => 'Düzeltildi'] + $fields + ['revision' => 1])
        ->assertOk()->assertJsonPath('data.appointment_id', $visit->id);
    $this->putJson($path.'/'.$entry, $fields + ['revision' => 2, 'offer_id' => $offer->id])->assertOk()
        ->assertJsonPath('data.appointment_id', null)->assertJsonPath('data.offer_id', $offer->id);
    $this->assertDatabaseCount('branch_ledger_entries', 1);
    $this->getJson($reportPath.'?period=month&month=2026-12')->assertOk()
        ->assertJsonPath('data.totals.0.unattributed_income', '100.00');
    $this->putJson($path.'/'.$entry.'/status', ['status' => 'voided', 'revision' => 3])->assertOk();
    $this->getJson($reportPath.'?period=month&month=2026-12')->assertOk()->assertJsonCount(0, 'data.totals');
});
