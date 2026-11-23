<?php

namespace App\Models;

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
}
