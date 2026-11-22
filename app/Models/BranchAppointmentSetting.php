<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchAppointmentSetting extends Model
{
    public const int MAX_BUFFER_MINUTES = 1440;

    protected $fillable = [
        'branch_id',
        'slot_interval_minutes',
        'minimum_booking_notice_minutes',
        'maximum_booking_days',
        'same_day_booking_enabled',
        'online_booking_enabled',
        'cancellation_enabled',
        'cancellation_before_minutes',
        'rescheduling_enabled',
        'rescheduling_before_minutes',
        'default_appointment_status',
        'buffer_before_minutes',
        'buffer_after_minutes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'slot_interval_minutes' => 'integer',
            'minimum_booking_notice_minutes' => 'integer',
            'maximum_booking_days' => 'integer',
            'same_day_booking_enabled' => 'boolean',
            'online_booking_enabled' => 'boolean',
            'cancellation_enabled' => 'boolean',
            'cancellation_before_minutes' => 'integer',
            'rescheduling_enabled' => 'boolean',
            'rescheduling_before_minutes' => 'integer',
            'buffer_before_minutes' => 'integer',
            'buffer_after_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public static function initialStatusForOnlineBooking(int $branchId): string
    {
        $automaticallyConfirms = static::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->where('default_appointment_status', 'confirmed')
            ->exists();

        return $automaticallyConfirms ? 'confirmed' : 'pending';
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
