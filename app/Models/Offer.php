<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Offer extends Model
{
    protected $fillable = [
        'conversation_id',
        'business_id',
        'branch_id',
        'patient_profile_id',
        'treatment_id',
        'treatment_package_id',
        'package_snapshot',
        'created_by',
        'title',
        'description',
        'amount',
        'currency',
        'valid_until',
        'status',
        'responded_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'package_snapshot' => 'array',
            'amount' => 'decimal:2',
            'valid_until' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function hasActiveClinic(): bool
    {
        return $this->conversation()->withActiveClinic()
            ->where('business_id', $this->business_id)
            ->where('patient_profile_id', $this->patient_profile_id)
            ->exists()
            && ($this->branch_id === null || $this->branch()
                ->where('business_id', $this->business_id)->where('status', 'active')->exists());
    }

    #[Scope]
    protected function readyForClinicBooking(Builder $query, Branch $branch): Builder
    {
        return $query->where('offers.business_id', $branch->business_id)
            ->where('offers.status', 'accepted')
            ->whereNotNull('package_snapshot')
            ->where(fn (Builder $offer) => $offer->whereNull('valid_until')->orWhere('valid_until', '>=', now()))
            ->where(fn (Builder $offer) => $offer->whereNull('offers.branch_id')->orWhere('offers.branch_id', $branch->id))
            ->where(fn (Builder $offer) => $offer->whereNull('package_snapshot->branch_id')->orWhere('package_snapshot->branch_id', $branch->id))
            ->whereHas('conversation', fn (Builder $conversation) => $conversation->withActiveClinic()
                ->whereColumn('conversations.business_id', 'offers.business_id')
                ->whereColumn('conversations.patient_profile_id', 'offers.patient_profile_id')
                ->where(fn (Builder $scope) => $scope->whereNull('conversations.branch_id')->orWhere('conversations.branch_id', $branch->id)))
            ->whereDoesntHave('appointments');
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

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }

    public function treatmentPackage(): BelongsTo
    {
        return $this->belongsTo(TreatmentPackage::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function appointment(): HasOne
    {
        return $this->hasOne(Appointment::class);
    }
}
