<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TreatmentPackage extends Model
{
    protected $fillable = [
        'business_id',
        'branch_id',
        'name',
        'slug',
        'description',
        'package_type',
        'price',
        'currency',
        'included_services',
        'excluded_services',
        'duration_days',
        'includes_hotel',
        'includes_transfer',
        'is_offer_enabled',
        'is_active',
        'valid_from',
        'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'duration_days' => 'integer',
            'includes_hotel' => 'boolean',
            'includes_transfer' => 'boolean',
            'is_offer_enabled' => 'boolean',
            'is_active' => 'boolean',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    #[Scope]
    protected function publishedInCatalog(Builder $query): Builder
    {
        $today = today()->toDateString();

        return $query->where('treatment_packages.is_active', true)
            ->where(fn (Builder $packages) => $packages
                ->whereNull('valid_from')->orWhereDate('valid_from', '<=', $today))
            ->where(fn (Builder $packages) => $packages
                ->whereNull('valid_until')->orWhereDate('valid_until', '>=', $today))
            ->whereHas('business', fn (Builder $businesses) => $businesses
                ->where('status', 'active')->where('is_verified', true))
            ->where(fn (Builder $packages) => $packages->whereNull('treatment_packages.branch_id')
                ->orWhereHas('branch', fn (Builder $branches) => $branches->where('status', 'active')
                    ->whereColumn('branches.business_id', 'treatment_packages.business_id')));
    }

    #[Scope]
    protected function availableForEnquiries(Builder $query): Builder
    {
        return $query->publishedInCatalog()
            ->where('treatment_packages.is_offer_enabled', true)
            ->whereHas('treatments')
            ->whereDoesntHave('treatments', fn (Builder $treatments) => $treatments
                ->where(fn (Builder $invalid) => $invalid->where('treatments.is_active', false)
                    ->orWhere(fn (Builder $notOffered) => $notOffered
                        ->whereNotNull('treatment_packages.branch_id')
                        ->whereNotExists(fn ($offering) => $offering->selectRaw('1')->from('branch_treatment')
                            ->whereColumn('branch_treatment.branch_id', 'treatment_packages.branch_id')
                            ->whereColumn('branch_treatment.treatment_id', 'treatments.id')
                            ->where('branch_treatment.is_active', true)))))
            ->whereDoesntHave('services', fn (Builder $services) => $services
                ->where(fn (Builder $invalid) => $invalid->where('package_services.is_active', false)
                    ->orWhereColumn('package_services.business_id', '!=', 'treatment_packages.business_id')));
    }

    #[Scope]
    protected function withPublicContents(Builder $query): Builder
    {
        return $query->with([
            'business:id,name,city',
            'branch:id,name,city,district',
            'treatments' => fn ($relation) => $relation->where('treatments.is_active', true)
                ->select('treatments.id', 'treatments.name')->orderByPivot('sort_order'),
            'services' => fn ($relation) => $relation->where('package_services.is_active', true)
                ->select('package_services.id', 'package_services.name', 'package_services.business_id')->orderByPivot('sort_order'),
        ]);
    }

    public function isPublishedInCatalog(): bool
    {
        return $this->newQuery()->publishedInCatalog()->whereKey($this->getKey())->exists();
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function serviceItems(): HasMany
    {
        return $this->hasMany(
            PackageServiceItem::class,
            'treatment_package_id'
        )->orderBy('sort_order');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(
            PackageService::class,
            'package_service_items',
            'treatment_package_id',
            'package_service_id'
        )->withPivot([
            'quantity',
            'notes',
            'sort_order',
        ])->withTimestamps();
    }

    public function treatments(): BelongsToMany
    {
        return $this->belongsToMany(
            Treatment::class,
            'treatment_package_items',
            'treatment_package_id',
            'treatment_id'
        )->withPivot([
            'quantity',
            'notes',
            'sort_order',
        ])->withTimestamps();
    }
}
