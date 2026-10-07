<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmPipelineStage extends Model
{
    protected $fillable = [
        'business_id',
        'name',
        'slug',
        'color',
        'sort_order',
        'is_won',
        'is_lost',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_won' => 'boolean',
            'is_lost' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(
            CrmLead::class,
            'pipeline_stage_id'
        );
    }

    public function stageHistories(): HasMany
    {
        return $this->hasMany(
            CrmPipelineStageHistory::class,
            'to_stage_id'
        );
    }
}