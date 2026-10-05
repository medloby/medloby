<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingCalendarRule extends Model
{
    protected $fillable = [
        'branch_id',
        'default_status',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(
            BookingCalendarOverride::class,
            'branch_id',
            'branch_id'
        );
    }
}