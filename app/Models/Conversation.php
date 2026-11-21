<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = [
        'business_id',
        'branch_id',
        'patient_profile_id',
        'treatment_package_id',
        'crm_lead_id',
        'subject',
        'status',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function withActiveClinic(Builder $query): Builder
    {
        return $query->whereHas('business', fn (Builder $clinic) => $clinic
            ->where('status', 'active')->where('is_verified', true))
            ->where(fn (Builder $scope) => $scope->whereNull('conversations.branch_id')
                ->orWhereHas('branch', fn (Builder $branch) => $branch
                    ->whereColumn('branches.business_id', 'conversations.business_id')->where('branches.status', 'active')));
    }

    #[Scope]
    protected function accessibleToClinic(Builder $query, User $user): Builder
    {
        return $query->withActiveClinic()
            ->whereHas('business.memberships', fn (Builder $memberships) => $memberships
                ->where('user_id', $user->id)->where('is_active', true)
                ->where(fn (Builder $scope) => $scope->where('role', 'business_owner')
                    ->orWhereNull('conversations.branch_id')
                    ->orWhereHas('branches', fn (Builder $branches) => $branches
                        ->whereColumn('branches.id', 'conversations.branch_id')
                        ->where('business_user_branch.is_active', true))));
    }

    #[Scope]
    protected function visibleTo(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $scope) => $scope
            ->whereHas('patientProfile', fn (Builder $patient) => $patient->where('user_id', $user->id))
            ->orWhere(fn (Builder $clinic) => $clinic->accessibleToClinic($user)));
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function patientProfile(): BelongsTo
    {
        return $this->belongsTo(PatientProfile::class);
    }

    public function treatmentPackage(): BelongsTo
    {
        return $this->belongsTo(TreatmentPackage::class);
    }

    public function crmLead(): BelongsTo
    {
        return $this->belongsTo(
            CrmLead::class,
            'crm_lead_id'
        );
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }
}
