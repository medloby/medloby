<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    public const array ACTION_PERMISSIONS = [
        'confirm' => 'appointments.confirm',
        'cancel' => 'appointments.cancel',
        'reschedule' => 'appointments.reschedule',
        'complete' => 'appointments.complete',
        'no-show' => 'appointments.mark_no_show',
    ];

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

    public function availableActions(): array
    {
        return match ($this->status) {
            'pending' => ['confirm', 'cancel', 'reschedule'],
            'confirmed' => ['complete', 'no-show', 'cancel', 'reschedule'],
            default => [],
        };
    }

    public function patientActions(Carbon $now): array
    {
        if (! in_array($this->status, ['pending', 'confirmed'], true) || $this->starts_at->lte($now)) {
            return [];
        }

        $branch = $this->branch;
        if (! $branch || $branch->id !== $this->branch_id || $branch->business_id !== $this->business_id
            || $branch->status !== 'active' || ! $branch->business?->allowsCrmAccess()) {
            return [];
        }

        $settings = $branch->appointmentSetting;
        if (! $settings?->is_active) {
            $settings = null;
        }

        $actions = [];
        $defaultNotice = BranchAppointmentSetting::DEFAULT_PATIENT_ACTION_NOTICE_MINUTES;
        if (($settings?->cancellation_enabled ?? true)
            && $this->starts_at->gte($now->copy()->addMinutes($settings?->cancellation_before_minutes ?? $defaultNotice))) {
            $actions[] = 'cancel';
        }
        if (($settings?->rescheduling_enabled ?? true) && ($settings?->online_booking_enabled ?? true)
            && $this->starts_at->gte($now->copy()->addMinutes($settings?->rescheduling_before_minutes ?? $defaultNotice))
            && $this->doctor?->is_public && $this->doctor->status === 'active' && $this->treatment?->is_active) {
            $actions[] = 'reschedule';
        }

        return $actions;
    }

    public function belongsToPatient(User $user): bool
    {
        return $this->patientProfile?->user_id === $user->id;
    }

    public static function clinicLocalNow(): Carbon
    {
        // Takvim saatleri veritabanında yerel duvar saati olarak tutulur.
        return Carbon::now('Europe/Istanbul')->shiftTimezone(config('app.timezone'));
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

    public function currentVersion(): self
    {
        $appointment = $this;

        while ($appointment->status === 'rescheduled') {
            $replacement = $appointment->rescheduledAppointments()
                ->where('id', '>', $appointment->id)
                ->where('business_id', $appointment->business_id)
                ->where('branch_id', $appointment->branch_id)
                ->where('patient_profile_id', $appointment->patient_profile_id)
                ->where(fn (Builder $query) => $query->whereNull('offer_id')->orWhere('offer_id', $appointment->offer_id))
                ->latest('id')->first();

            if (! $replacement) {
                break;
            }

            $appointment = $replacement;
        }

        return $appointment;
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(AppointmentStatusHistory::class);
    }
}
