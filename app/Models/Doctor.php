<?php

namespace App\Models;

use Closure;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doctor extends Model
{
    use HasFactory;

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

    /**
     * The query must join doctor_branch for the selected branch.
     *
     * @param  Closure(Builder<Person>): Builder<Person>  $inClinic
     */
    #[Scope]
    protected function withPublicBookingAssignment(Builder $query, Closure $inClinic, ?string $today = null): Builder
    {
        $today ??= Appointment::clinicLocalNow()->toDateString();

        return $query->where('doctors.status', 'active')->where('doctors.is_public', true)
            ->where('doctor_branch.status', 'active')
            ->where(fn (Builder $assignment) => $assignment->whereNull('doctor_branch.end_date')
                ->orWhere('doctor_branch.end_date', '>=', $today))
            ->whereHas('person', fn (Builder $person) => $inClinic($person->where('people.status', 'active')));
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
