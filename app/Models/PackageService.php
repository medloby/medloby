<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PackageService extends Model
{
    protected $fillable = [
        'business_id',
        'name',
        'slug',
        'service_type',
        'description',
        'unit',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    #[Scope]
    protected function publishedInCatalog(Builder $query): Builder
    {
        return $query->where('package_services.is_active', true)
            ->where(fn (Builder $services) => $services->whereNull('package_services.business_id')
                ->orWhereHas('business', fn (Builder $business) => $business->where('status', 'active')->where('is_verified', true)));
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(
            TreatmentPackage::class,
            'package_service_items',
            'package_service_id',
            'treatment_package_id'
        )->withPivot([
            'quantity',
            'notes',
            'sort_order',
        ])->withTimestamps();
    }
}
