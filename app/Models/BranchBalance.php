<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BranchBalance extends Model
{
    protected $fillable = [
        'branch_id', 'patient_profile_id', 'counterparty', 'request_key', 'direction',
        'description', 'amount_minor', 'currency', 'due_on', 'voided_at',
    ];

    protected $attributes = ['revision' => 1];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'voided_at' => 'datetime', 'amount_minor' => 'integer', 'revision' => 'integer'];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(BranchLedgerEntry::class);
    }

    public function activeEntries(): HasMany
    {
        return $this->entries()->whereNull('voided_at');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(PatientProfile::class, 'patient_profile_id');
    }

    #[Scope]
    protected function withSettlementTotal(Builder $query): Builder
    {
        return $query->addSelect(['settled_minor' => $this->settlementTotalQuery()]);
    }

    #[Scope]
    protected function overdue(Builder $query, CarbonImmutable $today): Builder
    {
        return $query->whereNull('branch_balances.voided_at')->where('branch_balances.due_on', '<', $today->toDateString())
            ->where('branch_balances.amount_minor', '>', $this->settlementTotalQuery());
    }

    /** @return Builder<BranchLedgerEntry> */
    private function settlementTotalQuery(): Builder
    {
        return BranchLedgerEntry::query()->whereColumn('branch_balance_id', 'branch_balances.id')
            ->whereColumn('branch_ledger_entries.branch_id', 'branch_balances.branch_id')->whereNull('voided_at')
            ->selectRaw('COALESCE(SUM(amount_minor), 0)');
    }
}
