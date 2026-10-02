<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TreatmentCategory extends Model
{
    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'image',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(
            TreatmentCategory::class,
            'parent_id'
        );
    }

    public function children(): HasMany
    {
        return $this->hasMany(
            TreatmentCategory::class,
            'parent_id'
        );
    }

    public function treatments(): HasMany
    {
        return $this->hasMany(Treatment::class);
    }
}