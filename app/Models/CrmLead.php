<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmLead extends Model
{
    protected $fillable = [
        'business_id',
        'branch_id',
        'patient_profile_id',
        'assigned_business_user_id',
        'pipeline_stage_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'country_code',
        'source',
        'status',
        'priority',
        'notes',
        'last_contacted_at',
        'next_follow_up_at',
        'converted_at',
        'lost_at',
        'lost_reason',
    ];

    protected function casts(): array
    {
        return [
            'last_contacted_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
            'converted_at' => 'datetime',
            'lost_at' => 'datetime',
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

    public function patientProfile(): BelongsTo
    {
        return $this->belongsTo(
            PatientProfile::class
        );
    }

    public function assignedBusinessUser(): BelongsTo
    {
        return $this->belongsTo(
            BusinessUser::class,
            'assigned_business_user_id'
        );
    }

    public function pipelineStage(): BelongsTo
    {
        return $this->belongsTo(
            CrmPipelineStage::class,
            'pipeline_stage_id'
        );
    }

    public function pipelineStageHistories(): HasMany
    {
        return $this->hasMany(
            CrmPipelineStageHistory::class,
            'crm_lead_id'
        )->orderBy('changed_at');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(
            CrmActivity::class,
            'crm_lead_id'
        )->orderByDesc('occurred_at');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(
            CrmFollowUp::class,
            'crm_lead_id'
        )->orderBy('scheduled_at');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(
            Conversation::class,
            'crm_lead_id'
        )->orderByDesc('last_message_at');
    }
}