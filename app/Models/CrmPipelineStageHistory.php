<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmPipelineStageHistory extends Model
{
    protected $fillable = [
        'crm_lead_id',
        'from_stage_id',
        'to_stage_id',
        'changed_by_business_user_id',
        'notes',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(
            CrmLead::class,
            'crm_lead_id'
        );
    }

    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(
            CrmPipelineStage::class,
            'from_stage_id'
        );
    }

    public function toStage(): BelongsTo
    {
        return $this->belongsTo(
            CrmPipelineStage::class,
            'to_stage_id'
        );
    }

    public function changedByBusinessUser(): BelongsTo
    {
        return $this->belongsTo(
            BusinessUser::class,
            'changed_by_business_user_id'
        );
    }
}