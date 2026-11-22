<?php

namespace App\Models;

use Database\Factories\BranchLedgerEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchLedgerEntry extends Model
{
    /** @use HasFactory<BranchLedgerEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'request_key', 'entry_date', 'kind', 'amount_minor', 'currency', 'description',
        'created_by_user_id', 'updated_by_user_id',
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
}
