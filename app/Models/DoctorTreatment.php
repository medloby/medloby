<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorTreatment extends Model
{
    protected $table = 'doctor_treatment';

    protected $fillable = [
        'doctor_id',
        'treatment_id',
        'duration_minutes',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }
}