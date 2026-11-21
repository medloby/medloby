<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatientProfile extends Model
{
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'phone',
        'birth_date',
        'gender',
        'country_code',
        'city',
        'preferred_language',
        'preferred_currency',
        'profile_photo',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }

    #[Scope]
    protected function knownToBranch(Builder $query, Branch $branch): Builder
    {
        $inBranch = fn (Builder $records) => $records->where('business_id', $branch->business_id)
            ->where('branch_id', $branch->id);

        return $query->where(fn (Builder $patient) => $patient
            ->whereHas('conversations', $inBranch)
            ->orWhereHas('appointments', fn (Builder $appointments) => $inBranch($appointments)
                ->where(fn (Builder $origin) => $origin
                    ->whereHas('statusHistories', fn (Builder $history) => $history
                        ->whereNull('old_status')
                        ->whereColumn('changed_by_user_id', 'patient_profiles.user_id'))
                    ->orWhereHas('offer', fn (Builder $offer) => $offer
                        ->whereColumn('offers.business_id', 'appointments.business_id')
                        ->whereColumn('offers.patient_profile_id', 'appointments.patient_profile_id')
                        ->where(fn (Builder $scope) => $scope->whereNull('offers.branch_id')
                            ->orWhereColumn('offers.branch_id', 'appointments.branch_id'))
                        ->where('offers.status', 'accepted')))));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function waitingLists(): HasMany
    {
        return $this->hasMany(WaitingList::class);
    }

    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }
}
