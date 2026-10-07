<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmFollowUp extends Model
{
    protected $fillable = [
        'business_id',
        'branch_id',
        'crm_lead_id',
        'assigned_business_user_id',
        'type',
        'title',
        'notes',
        'scheduled_at',
        'completed_at',
        'cancelled_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(
            Business::class
        );
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(
            Branch::class
        );
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(
            CrmLead::class,
            'crm_lead_id'
        );
    }

    public function assignedBusinessUser(): BelongsTo
    {
        return $this->belongsTo(
            BusinessUser::class,
            'assigned_business_user_id'
        );
    }
}