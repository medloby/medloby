<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Treatment extends Model
{
    protected $fillable = [
        'treatment_category_id',
        'name',
        'slug',
        'description',
        'duration_minutes',
        'preparation',
        'aftercare',
        'included_services',
        'excluded_services',
        'image',
        'is_online_bookable',
        'is_offer_enabled',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'is_online_bookable' => 'boolean',
            'is_offer_enabled' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function treatmentCategory(): BelongsTo
    {
        return $this->belongsTo(
            TreatmentCategory::class,
            'treatment_category_id'
        );
    }

    public function doctors(): BelongsToMany
    {
        return $this->belongsToMany(
            Doctor::class,
            'doctor_treatment'
        )->withPivot([
            'duration_minutes',
            'is_active',
            'notes',
        ])->withTimestamps();
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(
            TreatmentPackage::class,
            'treatment_package_items',
            'treatment_id',
            'treatment_package_id'
        )->withPivot([
            'quantity',
            'notes',
            'sort_order',
        ])->withTimestamps();
    }

    public function prices(): HasMany
    {
        return $this->hasMany(TreatmentPrice::class);
    }
}