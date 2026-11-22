<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Business extends Model
{
    use HasFactory;

    public const array MEMBERSHIP_PLANS = [
        'starter' => 'Başlangıç',
        'standard' => 'Standart',
        'professional' => 'Profesyonel',
    ];

    protected $fillable = [
        'name',
        'slug',
        'type',
        'membership_plan',
        'description',
        'email',
        'phone',
        'website',
        'public_contact_fields',
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

    protected $hidden = ['public_contact_fields', 'membership_plan'];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_verified' => 'boolean',
            'public_contact_fields' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    public function allowsCrmAccess(): bool
    {
        return $this->status === 'active' && $this->is_verified;
    }

    public function membershipPlanLabel(): ?string
    {
        return self::MEMBERSHIP_PLANS[$this->membership_plan ?? ''] ?? null;
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function uniqueBranchSlug(string $name): string
    {
        $base = Str::substr(Str::slug($name), 0, 255) ?: 'merkez';
        $slug = $base;
        $counter = 2;

        while ($this->branches()->where('slug', $slug)->exists()) {
            $suffix = '-'.$counter++;
            $slug = Str::substr($base, 0, 255 - strlen($suffix)).$suffix;
        }

        return $slug;
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

    public function latestApplicationReview(): HasOne
    {
        return $this->reviews()->one()->ofMany(
            ['reviewed_at' => 'max', 'id' => 'max'],
            fn ($query) => $query->whereIn('decision', ['approved', 'rejected']),
        );
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
