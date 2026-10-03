<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessReview extends Model
{
    protected $fillable = [
        'business_id',
        'reviewed_by_user_id',
        'decision',
        'reviewed_at',
        'notes',
        'rejection_reason',
        'previous_status',
        'new_status',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by_user_id'
        );
    }
}