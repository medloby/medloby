<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaitingList extends Model
{
    public const string ONLINE_BOOKING = 'online_booking';

    protected $fillable = [
        'business_id',
        'branch_id',
        'patient_profile_id',
        'doctor_id',
        'treatment_id',
        'preferred_date',
        'preferred_start_time',
        'preferred_end_time',
        'patient_name',
        'patient_phone',
        'patient_email',
        'source',
        'status',
        'notes',
        'notified_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
            'notified_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function onlineBooking(Builder $query): Builder
    {
        return $query->where('source', self::ONLINE_BOOKING);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function patientProfile(): BelongsTo
    {
        return $this->belongsTo(PatientProfile::class);
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
