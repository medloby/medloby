<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    protected $fillable = [
        'business_id',
        'branch_id',
        'patient_profile_id',
        'doctor_id',
        'treatment_id',
        'offer_id',
        'starts_at',
        'ends_at',
        'status',
        'source',
        'patient_name',
        'patient_phone',
        'patient_email',
        'notes',
        'cancellation_reason',
        'cancelled_at',
        'cancelled_by_user_id',
        'rescheduled_from_appointment_id',
        'rescheduled_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'rescheduled_at' => 'datetime',
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

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function rescheduledFrom(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'rescheduled_from_appointment_id'
        );
    }

    public function rescheduledAppointments(): HasMany
    {
        return $this->hasMany(
            self::class,
            'rescheduled_from_appointment_id'
        );
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(AppointmentStatusHistory::class);
    }
}