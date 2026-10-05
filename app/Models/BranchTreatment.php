<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class BranchTreatment extends Pivot
{
    protected $table = 'branch_treatment';

    public $incrementing = true;

    protected $fillable = [
        'branch_id',
        'treatment_id',
        'is_active',
        'is_online_bookable',
        'is_offer_enabled',
        'duration_minutes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_online_bookable' => 'boolean',
            'is_offer_enabled' => 'boolean',
            'duration_minutes' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }
}