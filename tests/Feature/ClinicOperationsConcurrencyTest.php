<?php

use App\Models\Branch;
use App\Models\BranchBalance;
use App\Models\BranchInventoryItem;
use App\Models\BranchInventoryMovement;
use App\Models\BranchLedgerEntry;
use App\Models\User;

test('concurrent collections neither duplicate receipts nor exceed the outstanding balance', function (bool $retry) {
    $this->withConcurrentDatabase(function (string $directory, array $settings) use ($retry): void {
        $branch = Branch::factory()->create();
        $owner = User::factory()->create();
        $branch->business->memberships()->create(['user_id' => $owner->id, 'role' => 'business_owner', 'is_active' => true]);
        $balance = BranchBalance::create([
            'branch_id' => $branch->id, 'counterparty' => 'Sentetik hesap', 'direction' => 'receivable',
            'description' => 'Tahsilat provası', 'amount_minor' => 10050, 'currency' => 'TRY', 'request_key' => str_repeat('a', 32),
        ]);
        $receipt = [
            'kind' => 'income', 'entry_date' => '2026-12-01', 'description' => 'Prova tahsilatı',
            'amount' => '60.25', 'currency' => 'TRY', 'branch_balance_id' => $balance->id, 'request_key' => str_repeat('b', 32),
        ];
        $submission = ['user_id' => $owner->id, 'path' => "/api/branches/{$branch->id}/crm/ledger", 'payload' => $receipt];
        $second = $submission;
        if (! $retry) {
            $second['payload']['request_key'] = str_repeat('c', 32);
        }

        $result = $this->concurrentPostRequests($directory, $settings, BranchLedgerEntry::class, $submission, $second);

        expect($result['first'][0])->toBe(201)->and($result['second'][0])->toBe($retry ? 200 : 422);
        if ($retry) {
            expect($result['second'][1]['data']['id'])->toBe($result['first'][1]['data']['id']);
        } else {
            expect($result['second'][1]['errors'])->toHaveKey('amount');
        }
        $this->assertDatabaseCount('branch_ledger_entries', 1);
        $this->actingAs($owner, 'sanctum')->getJson("/api/branches/{$branch->id}/crm/balances/{$balance->id}")
            ->assertOk()->assertJsonPath('data.remaining', '40.25')->assertJsonPath('data.settled', '60.25');
    });
})->with(['same receipt retry' => true, 'second payment exceeds remainder' => false]);

test('two concurrent stock issues cannot spend the same available units', function () {
    $this->withConcurrentDatabase(function (string $directory, array $settings): void {
        $branch = Branch::factory()->create();
        $owner = User::factory()->create();
        $branch->business->memberships()->create(['user_id' => $owner->id, 'role' => 'business_owner', 'is_active' => true]);
        $item = new BranchInventoryItem(['branch_id' => $branch->id, 'code' => 'PROVA', 'name' => 'Sentetik stok', 'unit' => 'Adet']);
        $item->forceFill(['on_hand' => 5])->save();
        $submission = [
            'user_id' => $owner->id, 'path' => "/api/branches/{$branch->id}/crm/inventory/{$item->id}/movements",
            'payload' => ['direction' => 'out', 'quantity' => 4, 'description' => 'Prova çıkışı', 'request_key' => str_repeat('d', 32)],
        ];
        $second = $submission;
        $second['payload']['request_key'] = str_repeat('e', 32);

        $result = $this->concurrentPostRequests($directory, $settings, BranchInventoryMovement::class, $submission, $second);

        expect($result['first'][0])->toBe(201)->and($result['second'][0])->toBe(422)
            ->and($result['second'][1]['message'])->toBe('Çıkış miktarı mevcut stoğu aşıyor.');
        $this->assertDatabaseCount('branch_inventory_movements', 1);
        expect($item->refresh()->on_hand)->toBe(1);
    });
});
