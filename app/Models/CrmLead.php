<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmLead extends Model
{
    public const CONVERTED = 'converted';

    public const LOST = 'lost';

    private const LEGACY_CONVERTED = 'completed';

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

    protected function status(): Attribute
    {
        return Attribute::get(self::normalizeStatus(...));
    }

    public static function normalizeStatus(string $status): string
    {
        return $status === self::LEGACY_CONVERTED ? self::CONVERTED : $status;
    }

    public static function isFinalStatus(string $status): bool
    {
        return in_array(self::normalizeStatus($status), [self::CONVERTED, self::LOST], true);
    }

    #[Scope]
    protected function forBranch(Builder $query, Branch $branch): Builder
    {
        return $query->where('crm_leads.business_id', $branch->business_id)
            ->where('crm_leads.branch_id', $branch->id);
    }

    #[Scope]
    protected function openForFollowUp(Builder $query): Builder
    {
        return $query->whereNotIn('crm_leads.status', [self::CONVERTED, self::LEGACY_CONVERTED, self::LOST]);
    }

    #[Scope]
    protected function withStatus(Builder $query, string $status): Builder
    {
        return self::normalizeStatus($status) === self::CONVERTED
            ? $query->whereIn('crm_leads.status', [self::CONVERTED, self::LEGACY_CONVERTED])
            : $query->where('crm_leads.status', $status);
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
        )->orderBy('changed_at')->orderBy('id');
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
