<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\BranchLedgerEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchLedgerEntry extends Model
{
    /** @use HasFactory<BranchLedgerEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'request_key', 'entry_date', 'kind', 'amount_minor', 'currency', 'description',
        'created_by_user_id', 'updated_by_user_id', 'appointment_id', 'offer_id',
        'branch_balance_id', 'expense_category', 'payment_channel',
    ];

    protected $attributes = ['revision' => 1];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'amount_minor' => 'integer',
            'revision' => 'integer',
            'voided_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    #[Scope]
    protected function recordedBetween(Builder $query, CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        return $query->whereBetween('entry_date', [$start->toDateString(), $end->endOfDay()->format('Y-m-d H:i:s.u')]);
    }

    #[Scope]
    protected function withAmountTotals(Builder $query, array $columns = ['currency']): Builder
    {
        return $query->addSelect($columns)->groupBy($columns)
            ->selectRaw("SUM(CASE WHEN kind = 'income' THEN amount_minor ELSE 0 END) AS income,
                SUM(CASE WHEN kind = 'expense' THEN amount_minor ELSE 0 END) AS expense");
    }

    /** @return array{currency: string, income: string, expense: string, net: string} */
    public function amountTotals(): array
    {
        return self::formatTotals($this->currency, (int) $this->income, (int) $this->expense);
    }

    /** @return array{currency: string, income: string, expense: string, net: string} */
    public static function formatTotals(string $currency, int $income, int $expense): array
    {
        return [
            'currency' => $currency,
            'income' => self::formatAmount($income),
            'expense' => self::formatAmount($expense),
            'net' => self::formatAmount($income - $expense),
        ];
    }

    public static function formatAmount(int $minor): string
    {
        return ($minor < 0 ? '-' : '').intdiv(abs($minor), 100).'.'.str_pad((string) (abs($minor) % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function parseAmount(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }
}
