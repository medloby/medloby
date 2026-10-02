<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $fillable = [
        'business_id',
        'name',
        'slug',
        'phone',
        'email',
        'country_code',
        'city',
        'district',
        'address',
        'postal_code',
        'latitude',
        'longitude',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    public function doctors(): BelongsToMany
    {
        return $this->belongsToMany(
            Doctor::class,
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
            'branch_treatment'
        )->withPivot([
            'is_active',
            'is_online_bookable',
            'is_offer_enabled',
            'duration_minutes',
        ])->withTimestamps();
    }

    public function doctorWorkingHours(): HasMany
    {
        return $this->hasMany(DoctorWorkingHour::class);
    }

    public function doctorLeaves(): HasMany
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
}