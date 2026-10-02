<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreatmentPackageItem extends Model
{
    protected $fillable = [
        'treatment_package_id',
        'treatment_id',
        'quantity',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function treatmentPackage(): BelongsTo
    {
        return $this->belongsTo(
            TreatmentPackage::class,
            'treatment_package_id'
        );
    }

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(
            Treatment::class,
            'treatment_id'
        );
    }
}