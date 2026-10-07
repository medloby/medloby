<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'branch_id',
        'crm_lead_id',
        'assigned_business_user_id',
        'created_by_business_user_id',
        'title',
        'description',
        'type',
        'status',
        'priority',
        'due_at',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'crm_lead_id');
    }

    public function assignedBusinessUser(): BelongsTo
    {
        return $this->belongsTo(
            BusinessUser::class,
            'assigned_business_user_id'
        );
    }

    public function createdByBusinessUser(): BelongsTo
    {
        return $this->belongsTo(
            BusinessUser::class,
            'created_by_business_user_id'
        );
    }
}