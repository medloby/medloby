<?php

use App\Models\Branch;
use App\Models\BranchLedgerEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function clinicLedgerFixture(): array
{
    $branch = Branch::factory()->create();
    $owner = User::factory()->create();
    $branch->business->memberships()->create(['user_id' => $owner->id, 'role' => 'business_owner', 'is_active' => true]);

    return [$branch, $owner];
}

function ledgerEntryValues(string $suffix = 'a'): array
{
    return [
        'kind' => 'income', 'entry_date' => '2026-12-01', 'amount' => '0.10', 'currency' => 'TRY',
        'description' => 'Örnek tahsilat', 'request_key' => str_repeat($suffix, 32),
    ];
}

test('monthly branch summaries retain exact cents and separate currencies', function () {
    [$branch, $owner] = clinicLedgerFixture();
    $otherBranch = Branch::factory()->for($branch->business)->create();
    $this->travelTo(CarbonImmutable::parse('2026-11-30 22:45:00', 'UTC'));
    $url = "/api/branches/{$branch->id}/crm/ledger";
    $client = $this->actingAs($owner, 'sanctum');
    $client->postJson($url, ledgerEntryValues() + ['branch_id' => $otherBranch->id])->assertCreated()
        ->assertJsonPath('data.amount', '0.10')->assertJsonMissingPath('data.request_key')->assertJsonMissingPath('data.created_by_user_id');
    $client->postJson($url, array_replace(ledgerEntryValues('b'), ['amount' => '0.20']))->assertCreated();
    $client->postJson($url, array_replace(ledgerEntryValues('c'), ['amount' => '0.05', 'kind' => 'expense']))->assertCreated();
    $client->postJson($url, array_replace(ledgerEntryValues('d'), ['amount' => '12.40', 'kind' => 'expense', 'currency' => 'EUR']))->assertCreated();
    BranchLedgerEntry::factory()->for($branch)->create(['entry_date' => '2026-11-30']);
    BranchLedgerEntry::factory()->for($otherBranch)->create(['entry_date' => '2026-12-01']);
    $this->assertDatabaseHas('branch_ledger_entries', ['branch_id' => $branch->id, 'request_key' => str_repeat('a', 32), 'amount_minor' => 10]);
    $client->getJson($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.month', '2026-12')->assertJsonPath('data.today', '2026-12-01')->assertJsonPath('data.can_manage', true)
        ->assertJsonCount(4, 'data.entries')->assertJsonPath('data.totals', [
            ['currency' => 'EUR', 'income' => '0.00', 'expense' => '12.40', 'net' => '-12.40'],
            ['currency' => 'TRY', 'income' => '0.30', 'expense' => '0.05', 'net' => '0.25'],
        ]);
});

test('cash entry retries are idempotent and revisions protect corrections and voided entries', function () {
    [$branch, $owner] = clinicLedgerFixture();
    $url = "/api/branches/{$branch->id}/crm/ledger";
    $values = ledgerEntryValues();
    $id = $this->actingAs($owner, 'sanctum')->postJson($url, $values)->assertCreated()->json('data.id');
    $this->postJson($url, $values)->assertOk()->assertJsonPath('data.id', $id);
    $this->postJson($url, array_replace($values, ['amount' => '0.20']))->assertConflict();
    $this->assertDatabaseCount('branch_ledger_entries', 1);

    $path = $url.'/'.$id;
    $second = array_replace($values, ['amount' => '1.41', 'revision' => 1]);
    $this->putJson($path, $second)->assertOk()->assertJsonPath('data.revision', 2)->assertJsonPath('data.amount', '1.41');
    $this->putJson($path, $second)->assertOk()->assertJsonPath('data.revision', 2);
    $this->putJson($path, $values + ['revision' => 1])->assertConflict();
    $this->putJson($path.'/status', ['status' => 'voided', 'revision' => 1])->assertConflict();
    $this->putJson($path.'/status', ['status' => 'voided', 'revision' => 2])->assertOk()->assertJsonPath('data.revision', 3);
    $this->putJson($path, $values + ['revision' => 3])->assertConflict();
    $this->getJson($url.'?month=2026-12')->assertOk()->assertJsonPath('data.totals', [])->assertJsonCount(0, 'data.entries');
    $this->getJson($url.'?month=2026-12&status=voided')->assertOk()->assertJsonPath('data.entries.0.status', 'voided');
    $this->putJson($path.'/status', ['status' => 'active', 'revision' => 3])->assertOk()->assertJsonPath('data.revision', 4);
    $this->getJson($url.'?month=2026-12')->assertOk()->assertJsonPath('data.totals.0.net', '1.41');
    $this->assertDatabaseHas('branch_ledger_entries', ['id' => $id, 'amount_minor' => 141, 'voided_at' => null, 'revision' => 4]);
});

test('finance viewing and management permissions apply to the assigned branch only', function () {
    [$branch, $owner] = clinicLedgerFixture();
    $other = Branch::factory()->for($branch->business)->create();
    $foreignEntry = BranchLedgerEntry::factory()->for($other)->create();
    $staff = User::factory()->create();
    $member = $branch->business->memberships()->create(['user_id' => $staff->id, 'role' => 'staff', 'is_active' => true]);
    $member->branches()->attach($branch, ['is_active' => true, 'permission_names' => ['finance.view']]);
    $member->branches()->attach($other, ['is_active' => true]);
    $url = "/api/branches/{$branch->id}/crm/ledger";
    $otherUrl = "/api/branches/{$other->id}/crm/ledger";
    $this->getJson($url)->assertUnauthorized();
    $this->actingAs(User::factory()->create(['is_admin' => true]), 'sanctum')->getJson($url)->assertForbidden();
    $this->actingAs($staff, 'sanctum')->getJson($url)->assertOk()->assertJsonPath('data.can_manage', false);
    $this->getJson($otherUrl)->assertForbidden();
    $this->postJson($url, ledgerEntryValues())->assertForbidden();
    $this->getJson("/api/branches/{$branch->id}/crm/dashboard")->assertOk()->assertJsonPath('data.can_view_finances', true);
    $this->getJson("/api/branches/{$other->id}/crm/dashboard")->assertOk()->assertJsonPath('data.can_view_finances', false);

    $permissionUrl = "/api/branches/{$branch->id}/operations/staff/{$member->id}/permissions";
    $this->actingAs($owner, 'sanctum')->putJson($permissionUrl, ['permissions' => ['finance.manage']])->assertOk();
    $this->actingAs($staff, 'sanctum')->getJson($url)->assertOk()->assertJsonPath('data.can_manage', true);
    $this->postJson($url, ledgerEntryValues())->assertCreated();
    $this->putJson($url.'/'.$foreignEntry->id, ledgerEntryValues() + ['revision' => 1])->assertNotFound();
    $this->getJson($otherUrl)->assertForbidden();
    $this->actingAs($owner, 'sanctum')->putJson($permissionUrl, ['permissions' => []])->assertOk();
    $this->actingAs($staff, 'sanctum')->getJson($url)->assertForbidden();
});

test('unsupported dates and precision are rejected and summaries cover all pages', function () {
    [$branch, $owner] = clinicLedgerFixture();
    BranchLedgerEntry::factory()->for($branch)->count(21)->create(['entry_date' => '2026-12-01', 'amount_minor' => 1]);
    $url = "/api/branches/{$branch->id}/crm/ledger";
    $this->actingAs($owner, 'sanctum')->getJson($url.'?month=0000-01')->assertUnprocessable()->assertJsonValidationErrors('month');
    $this->postJson($url, array_replace(ledgerEntryValues(), ['entry_date' => '0000-01-01', 'amount' => '1.001']))
        ->assertUnprocessable()->assertJsonValidationErrors(['entry_date', 'amount']);
    $this->postJson($url, array_replace(ledgerEntryValues(), ['amount' => '0.00']))->assertUnprocessable()->assertJsonValidationErrors('amount');
    $this->getJson($url.'?month=2026-12&page=2')->assertOk()->assertJsonCount(1, 'data.entries')
        ->assertJsonPath('data.totals.0.income', '0.21');
    $this->assertDatabaseCount('branch_ledger_entries', 21);
});
