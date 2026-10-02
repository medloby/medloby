<?php

namespace App\Models;

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