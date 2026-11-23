<?php

use App\Models\Branch;
use App\Models\BranchLedgerEntry;
use App\Models\CrmLead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function expandedOperationsClinic(): array
{
    $branch = Branch::factory()->create();
    $owner = User::factory()->create();
    $branch->business->memberships()->create(['user_id' => $owner->id, 'role' => 'business_owner', 'is_active' => true]);

    return [$branch, $owner, '/api/branches/'.$branch->id.'/crm'];
}

test('patient payments and branch payables post once to the ledger and outstanding cash records reconcile', function () {
    [$branch, $owner, $url] = expandedOperationsClinic();
    $patient = User::factory()->create()->patientProfile()->create(['first_name' => 'Ödeme', 'last_name' => 'Hastası']);
    $patient->conversations()->create(['business_id' => $branch->business_id, 'branch_id' => $branch->id]);
    $this->actingAs($owner, 'sanctum')->getJson($url.'/balances/patients?q=Ödeme')->assertOk()->assertJsonPath('data.0.id', $patient->id);
    $balance = ['direction' => 'receivable', 'patient_profile_id' => $patient->id, 'description' => 'Klinik hizmeti',
        'amount' => '100.50', 'currency' => 'TRY', 'due_on' => '2026-12-31', 'request_key' => str_repeat('a', 32)];
    $id = $this->postJson($url.'/balances', $balance)->assertCreated()->assertJsonPath('data.remaining', '100.50')->json('data.id');
    $this->postJson($url.'/balances', $balance)->assertOk()->assertJsonPath('data.id', $id);
    $receipt = ['kind' => 'income', 'entry_date' => '2026-12-31', 'description' => 'İlk tahsilat', 'amount' => '60.25',
        'currency' => 'TRY', 'payment_channel' => 'bank', 'branch_balance_id' => $id, 'request_key' => str_repeat('b', 32)];
    $entry = $this->postJson($url.'/ledger', $receipt)->assertCreated()->json('data.id');
    $this->postJson($url.'/ledger', $receipt)->assertOk()->assertJsonPath('data.id', $entry);
    $this->getJson($url.'/balances/'.$id)->assertOk()->assertJsonPath('data.remaining', '40.25')
        ->assertJsonPath('data.movements.data.0.amount', '60.25');
    $second = array_replace($receipt, ['request_key' => str_repeat('c', 32), 'payment_channel' => 'cash']);
    $this->postJson($url.'/ledger', $second)->assertUnprocessable()->assertJsonValidationErrors('amount');
    $this->postJson($url.'/ledger', array_replace($second, ['amount' => '40.25', 'currency' => 'USD']))
        ->assertUnprocessable()->assertJsonValidationErrors('branch_balance_id');
    $this->postJson($url.'/ledger', array_replace($second, ['amount' => '40.25']))->assertCreated();
    $this->getJson($url.'/balances/'.$id)->assertOk()->assertJsonPath('data.status', 'settled');
    $this->putJson($url.'/balances/'.$id.'/status', ['status' => 'voided', 'revision' => 1])->assertConflict();
    $this->putJson($url.'/ledger/'.$entry.'/status', ['status' => 'voided', 'revision' => 1])->assertOk();
    $this->getJson($url.'/balances/'.$id)->assertOk()->assertJsonPath('data.remaining', '60.25');

    $payable = $this->postJson($url.'/balances', [
        'direction' => 'payable', 'counterparty' => 'Test tedarikçisi', 'description' => 'Malzeme borcu',
        'amount' => '25.00', 'currency' => 'TRY', 'request_key' => str_repeat('d', 32),
    ])->assertCreated()->json('data.id');
    $this->postJson($url.'/ledger', array_replace($receipt, ['kind' => 'expense', 'description' => 'Malzeme ödemesi',
        'expense_category' => 'Malzeme', 'amount' => '20.25', 'payment_channel' => 'cash',
        'branch_balance_id' => $payable, 'request_key' => str_repeat('e', 32)]))->assertCreated();
    BranchLedgerEntry::factory()->for($branch)->create(['entry_date' => '2026-11-30', 'payment_channel' => 'cash', 'amount_minor' => 1100]);
    $this->getJson($url.'/balances/'.$payable)->assertOk()->assertJsonPath('data.remaining', '4.75');
    $this->getJson($url.'/ledger?month=2026-12&channel=cash')->assertOk()->assertJsonCount(2, 'data.entries')
        ->assertJsonPath('data.expense_categories.0.category', 'Malzeme')->assertJsonPath('data.expense_categories.0.amount', '20.25')
        ->assertJsonPath('data.accounts.0.net', '31.00')->assertJsonPath('data.totals.0.net', '20.00');
    $this->assertDatabaseCount('branch_ledger_entries', 4);

    $staff = User::factory()->create();
    $branch->business->memberships()->create(['user_id' => $staff->id, 'role' => 'staff', 'is_active' => true])->branches()->attach($branch);
    $this->actingAs($staff, 'sanctum')->getJson($url.'/balances')->assertForbidden();
});

test('inventory stock changes atomically, detects retries and warns at the configured threshold', function () {
    [$branch, $owner, $url] = expandedOperationsClinic();
    $path = $url.'/inventory';
    $this->actingAs($owner, 'sanctum');
    $fields = ['code' => 'IMP-1', 'name' => 'İmplant', 'unit' => 'Adet', 'minimum_stock' => 2];
    $item = $this->postJson($path, $fields)->assertCreated()->assertJsonPath('data.on_hand', 0)->json('data.id');
    $this->postJson($path, $fields)->assertOk()->assertJsonPath('data.id', $item);
    $this->postJson($path, ['name' => 'Başka'] + $fields)->assertConflict();
    $movement = $path.'/'.$item.'/movements';
    $receipt = ['direction' => 'in', 'quantity' => 5, 'description' => 'Mal kabul', 'request_key' => str_repeat('a', 32)];
    $this->postJson($movement, $receipt)->assertCreated()->assertJsonPath('data.on_hand', 5);
    $this->postJson($movement, $receipt)->assertOk()->assertJsonPath('data.on_hand', 5);
    $this->postJson($movement, ['quantity' => 6] + $receipt)->assertConflict();
    $issue = ['direction' => 'out', 'quantity' => 3, 'description' => 'Kullanım', 'request_key' => str_repeat('b', 32)];
    $this->postJson($movement, ['quantity' => 9] + $issue)->assertUnprocessable();
    $this->postJson($movement, $issue)->assertCreated()->assertJsonPath('data.low_stock', true)->assertJsonPath('data.on_hand', 2);
    $this->getJson($path.'?low=1')->assertOk()->assertJsonPath('data.low_stock_count', 1);
    $this->getJson($movement)->assertOk()->assertJsonCount(2, 'data.movements.data')->assertJsonPath('data.movements.data.0.balance_after', 2);
    $this->putJson($path.'/'.$item, ['unit' => 'Kutu', 'revision' => 3] + $fields)->assertConflict();

    $staff = User::factory()->create();
    $member = $branch->business->memberships()->create(['user_id' => $staff->id, 'role' => 'staff', 'is_active' => true]);
    $member->branches()->attach($branch, ['permission_names' => ['inventory.view']]);
    $this->actingAs($staff, 'sanctum')->getJson($path)->assertOk()->assertJsonPath('data.can_manage', false);
    $this->postJson($movement, $issue)->assertForbidden();
    $member->branches()->updateExistingPivot($branch, ['is_active' => false]);
    $this->getJson($movement)->assertForbidden();
});

test('the lead care plan lists all phases and follows the current same-patient appointment', function () {
    [$branch, $owner, $url] = expandedOperationsClinic();
    $patient = User::factory()->create()->patientProfile()->create(['first_name' => 'Prova', 'last_name' => 'Hasta']);
    $patient->conversations()->create(['business_id' => $branch->business_id, 'branch_id' => $branch->id]);
    $lead = CrmLead::create(['business_id' => $branch->business_id, 'branch_id' => $branch->id, 'patient_profile_id' => $patient->id]);
    $visit = $branch->appointments()->create(['business_id' => $branch->business_id, 'patient_profile_id' => $patient->id, 'status' => 'confirmed',
        'starts_at' => '2026-12-04 09:00:00', 'ends_at' => '2026-12-04 09:30:00']);
    $unrelated = $branch->appointments()->create(['business_id' => $branch->business_id, 'status' => 'confirmed',
        'starts_at' => '2026-12-04 10:00:00', 'ends_at' => '2026-12-04 10:30:00']);
    $path = $url.'/leads/'.$lead->id.'/care-steps';
    $payload = ['status' => 'planned', 'planned_on' => '2026-12-04', 'appointment_id' => $visit->id, 'revision' => 0];
    $this->actingAs($owner, 'sanctum')->getJson($path)->assertOk()->assertJsonCount(4, 'data.phases')->assertJsonCount(1, 'data.appointments');
    $this->putJson($path.'/consultation', ['appointment_id' => $unrelated->id] + $payload)->assertUnprocessable()->assertJsonValidationErrors('appointment_id');
    $this->putJson($path.'/consultation', $payload)->assertOk()->assertJsonPath('data.phases.0.appointment.id', $visit->id);
    $this->putJson($path.'/consultation', $payload)->assertOk()->assertJsonPath('data.phases.0.revision', 1);
    $this->putJson($path.'/consultation', ['status' => 'completed'] + $payload)->assertConflict();
    $visit->update(['status' => 'rescheduled']);
    $replacement = $branch->appointments()->create(['business_id' => $branch->business_id, 'patient_profile_id' => $patient->id, 'status' => 'confirmed',
        'starts_at' => '2026-12-05 09:00:00', 'ends_at' => '2026-12-05 09:30:00', 'rescheduled_from_appointment_id' => $visit->id]);
    $this->getJson($path)->assertOk()->assertJsonPath('data.phases.0.appointment.id', $replacement->id);
    $this->putJson($path.'/consultation', ['status' => 'completed', 'appointment_id' => $replacement->id, 'revision' => 1] + $payload)
        ->assertOk()->assertJsonPath('data.phases.0.status', 'completed')->assertJsonPath('data.phases.0.revision', 2);
    $other = Branch::factory()->for($branch->business)->create();
    $this->getJson('/api/branches/'.$other->id.'/crm/leads/'.$lead->id.'/care-steps')->assertNotFound();
});
