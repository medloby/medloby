<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'description',
        'email',
        'phone',
        'website',
        'country_code',
        'city',
        'district',
        'address',
        'postal_code',
        'latitude',
        'longitude',
        'status',
        'is_verified',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(BusinessUser::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(BusinessUserPermission::class);
    }

    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function treatmentPrices(): HasMany
    {
        return $this->hasMany(TreatmentPrice::class);
    }

    public function treatmentPackages(): HasMany
    {
        return $this->hasMany(TreatmentPackage::class);
    }

    public function packageServices(): HasMany
    {
        return $this->hasMany(PackageService::class);
    }

    public function calendarBlocks(): HasMany
    {
        return $this->hasMany(CalendarBlock::class);
    }

    public function waitingLists(): HasMany
    {
        return $this->hasMany(WaitingList::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function contractAcceptances(): HasMany
    {
        return $this->hasMany(
            ClinicContractAcceptance::class
        );
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(BusinessReview::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(
            BusinessStatusHistory::class
        );
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(
            BusinessNotificationPreference::class
        );
    }
}