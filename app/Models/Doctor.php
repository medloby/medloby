<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doctor extends Model
{
    protected $fillable = [
        'person_id',
        'license_number',
        'specialty',
        'bio',
        'profile_photo',
        'status',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(
            Branch::class,
            'doctor_branch'
        )->withPivot([
            'status',
            'start_date',
            'end_date',
            'notes',
        ])->withTimestamps();
    }

    public function treatments(): BelongsToMany
    {
        return $this->belongsToMany(
            Treatment::class,
            'doctor_treatment'
        )->withPivot([
            'duration_minutes',
            'is_active',
            'notes',
        ])->withTimestamps();
    }

    public function workingHours(): HasMany
    {
        return $this->hasMany(DoctorWorkingHour::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(DoctorLeave::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function calendarBlocks(): HasMany
    {
        return $this->hasMany(CalendarBlock::class);
    }

    public function waitingLists(): HasMany
    {
        return $this->hasMany(WaitingList::class);
    }

    public function treatmentPrices(): HasMany
    {
        return $this->hasMany(TreatmentPrice::class);
    }
}