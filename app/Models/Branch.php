<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'slug',
        'phone',
        'email',
        'public_contact_fields',
        'country_code',
        'city',
        'district',
        'address',
        'postal_code',
        'latitude',
        'longitude',
        'status',
    ];

    protected $hidden = ['public_contact_fields'];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'public_contact_fields' => 'array',
        ];
    }

    public static function lockForOperationsWrite(int $branchId): self
    {
        $query = static::query();
        $connection = $query->getModel()->getConnection();
        if ($connection->getDriverName() === 'sqlite') {
            // SQLite ignores row locks. Acquire its write lock before reading the branch state.
            $connection->affectingStatement('UPDATE branches SET id = id WHERE 1 = 0');
        }

        return $query->lockForUpdate()->findOrFail($branchId);
    }

    #[Scope]
    protected function bookableOnline(Builder $query): Builder
    {
        return $query->where('branches.status', 'active')
            ->whereHas('business', fn (Builder $business) => $business->where('status', 'active')->where('is_verified', true))
            ->whereDoesntHave('appointmentSetting', fn (Builder $settings) => $settings->where('is_active', true)->where('online_booking_enabled', false))
            ->whereHas('treatments', fn (Builder $treatments) => $treatments->where('treatments.is_active', true)
                ->where('branch_treatment.is_active', true)->where('branch_treatment.is_online_bookable', true)
                ->whereHas('doctors', fn (Builder $doctors) => $doctors
                    ->join('doctor_branch', 'doctor_branch.doctor_id', '=', 'doctors.id')
                    ->whereColumn('doctor_branch.branch_id', 'branches.id')->where('doctor_treatment.is_active', true)
                    ->withPublicBookingAssignment(fn (Builder $person) => $person->whereColumn('people.business_id', 'branches.business_id'))));
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    public function businessUsers(): BelongsToMany
    {
        return $this->belongsToMany(
            BusinessUser::class,
            'business_user_branch',
            'branch_id',
            'business_user_id'
        )
            ->withPivot([
                'is_active',
            ])
            ->withTimestamps();
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
        )
            ->using(BranchTreatment::class)
            ->withPivot([
                'is_active',
                'is_online_bookable',
                'is_offer_enabled',
                'duration_minutes',
            ])
            ->withTimestamps();
    }

    public function socialMediaAccounts(): HasMany
    {
        return $this->hasMany(
            SocialMediaAccount::class
        )->orderBy('sort_order');
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

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(BranchLedgerEntry::class);
    }

    public function calendarBlocks(): HasMany
    {
        return $this->hasMany(CalendarBlock::class);
    }

    public function waitingLists(): HasMany
    {
        return $this->hasMany(WaitingList::class);
    }

    public function appointmentSetting(): HasOne
    {
        return $this->hasOne(BranchAppointmentSetting::class);
    }

    public function bookingCalendarRule(): HasOne
    {
        return $this->hasOne(BookingCalendarRule::class);
    }

    public function bookingCalendarOverrides(): HasMany
    {
        return $this->hasMany(BookingCalendarOverride::class);
    }

    public function crmActivities(): HasMany
    {
        return $this->hasMany(CrmActivity::class);
    }

    public function crmFollowUps(): HasMany
    {
        return $this->hasMany(CrmFollowUp::class);
    }
}
