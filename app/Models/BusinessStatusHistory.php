<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessStatusHistory extends Model
{
    protected $fillable = [
        'business_id',
        'changed_by_user_id',
        'previous_status',
        'new_status',
        'reason',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'changed_by_user_id'
        );
    }
}