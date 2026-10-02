<?php

namespace App\Models;

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
}