<?php

namespace App\Services;

use App\Exceptions\AppointmentRuleViolation;
use App\Models\Appointment;
use App\Models\BranchAppointmentSetting;
use App\Models\CalendarBlock;
use App\Models\Doctor;
use App\Models\PatientProfile;
use App\Models\Treatment;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\DB;

class AppointmentAvailabilityService
{
    public function __construct(
        protected BookingCalendarService $bookingCalendarService
    ) {}

    public function runBookingTransaction(Closure $book): mixed
    {
        $connection = DB::connection();

        return $connection->transaction(function () use ($connection, $book) {
            if ($connection->getDriverName() === 'sqlite') {
                // SQLite ignores row locks. An empty update takes the write lock before any availability reads.
                $connection->affectingStatement('UPDATE doctors SET id = id WHERE 1 = 0');
            }

            return $book();
        }, 3);
    }

    public function lockPatient(?int $patientProfileId): void
    {
        // Serialize bookings for the same patient even when they involve different doctors.
        if ($patientProfileId !== null) {
            PatientProfile::query()->lockForUpdate()->findOrFail($patientProfileId, ['id']);
        }
    }

    public function ensurePatientAvailable(
        ?int $patientProfileId,
        Carbon $startsAt,
        Carbon $endsAt,
        string $message,
        ?int $ignoreAppointmentId = null,
        ?int $packageOfferId = null,
    ): void {
        $overlaps = Appointment::query()->useWritePdo()
            ->scheduledOverlapping($startsAt, $endsAt, $ignoreAppointmentId);
        if ($packageOfferId !== null && (clone $overlaps)->where('offer_id', $packageOfferId)->exists()) {
            throw new AppointmentRuleViolation('Paket randevularının saatleri çakışamaz.');
        }
        if ($patientProfileId !== null && $overlaps->where('patient_profile_id', $patientProfileId)->exists()) {
            throw new AppointmentRuleViolation($message);
        }
    }

    /**
     * Belirli doktor, şube ve tedavi için başlangıç saatinin
     * randevuya uygun olup olmadığını kontrol eder.
     */
    public function isAvailable(
        Doctor $doctor,
        int $branchId,
        Treatment $treatment,
        Carbon $startsAt,
        ?int $ignoreAppointmentId = null,
        bool $requireOnlineBookable = true,
        int $fallbackSlotIntervalMinutes = BranchAppointmentSetting::DEFAULT_SLOT_INTERVAL_MINUTES,
    ): bool {
        $now = Appointment::clinicLocalNow();
        $startsAt = Appointment::calendarDateTime($startsAt);
        if ($doctor->status !== 'active' || $startsAt->lte($now)) {
            return false;
        }

        $durationMinutes = $this->getDurationMinutes(
            $doctor,
            $branchId,
            $treatment
        );

        if ($durationMinutes === null || $durationMinutes <= 0) {
            return false;
        }

        $endsAt = $startsAt->copy()->addMinutes($durationMinutes);

        // 1. Doktor bu şubede aktif olarak çalışıyor mu?
        if (! $this->doctorWorksAtBranch(
            $doctor->id,
            $branchId,
            $startsAt
        )) {
            return false;
        }

        // 2. Şube bu tedaviyi aktif olarak sunuyor mu?
        if (! $this->branchOffersTreatment(
            $branchId,
            $treatment->id
        )) {
            return false;
        }

        // 3. Doktor bu tedaviyi aktif olarak yapıyor mu?
        if (! $this->doctorPerformsTreatment(
            $doctor->id,
            $treatment->id
        )) {
            return false;
        }

        $settings = BranchAppointmentSetting::query()->where('branch_id', $branchId)->where('is_active', true)->first([
            'online_booking_enabled', 'minimum_booking_notice_minutes', 'same_day_booking_enabled', 'maximum_booking_days', 'slot_interval_minutes',
            'buffer_before_minutes', 'buffer_after_minutes',
        ]);

        // 4. Çevrimiçi randevu için şubenin rezervasyon kurallarını kontrol et.
        if (
            $requireOnlineBookable &&
            ! $this->allowsOnlineBooking($branchId, $treatment->id, $startsAt, $now, $settings)
        ) {
            return false;
        }

        // 5. Booking Calendar tarih kontrolü.
        //
        // Klinik bu tarihi takvimden kapattıysa
        // doktorun çalışma saati uygun olsa bile
        // online randevu alınamaz.
        if (
            $requireOnlineBookable &&
            ! $this->bookingCalendarService->isDateOpen(
                $branchId,
                $startsAt
            )
        ) {
            return false;
        }

        $occupiedStart = $startsAt->copy()->subMinutes($settings?->buffer_before_minutes ?? 0);
        $occupiedEnd = $endsAt->copy()->addMinutes($settings?->buffer_after_minutes ?? 0);
        $slotIntervalMinutes = $requireOnlineBookable ? ($settings?->slot_interval_minutes ?? $fallbackSlotIntervalMinutes) : null;

        // 6. Hekimin çalışma saatleri hazırlık ve temizlik süresini de kapsamalı.
        if ($occupiedStart->lt($now) || ! $this->isWithinWorkingHours(
            $doctor->id,
            $branchId,
            $occupiedStart,
            $occupiedEnd,
            $startsAt,
            $slotIntervalMinutes,
        )) {
            return false;
        }

        // 7. Doktor izinli mi?
        if ($this->isOnLeave(
            $doctor->id,
            $branchId,
            $occupiedStart,
            $occupiedEnd
        )) {
            return false;
        }

        // 8. Manuel takvim bloğu var mı?
        if ($this->hasCalendarBlock(
            $branchId,
            $doctor->id,
            $occupiedStart,
            $occupiedEnd
        )) {
            return false;
        }

        // 9. Başka aktif randevuyla çakışıyor mu?
        if ($this->hasAppointmentConflict(
            $doctor->id,
            $occupiedStart,
            $occupiedEnd,
            $ignoreAppointmentId
        )) {
            return false;
        }

        return true;
    }

    /**
     * Doktor özel süresi > şube özel süresi > tedavi genel süresi.
     */
    public function getDurationMinutes(
        Doctor $doctor,
        int $branchId,
        Treatment $treatment
    ): ?int {
        $doctorDuration = DB::table('doctor_treatment')
            ->where('doctor_id', $doctor->id)
            ->where('treatment_id', $treatment->id)
            ->where('is_active', true)
            ->value('duration_minutes');

        if ($doctorDuration !== null) {
            return (int) $doctorDuration;
        }

        $branchDuration = DB::table('branch_treatment')
            ->where('branch_id', $branchId)
            ->where('treatment_id', $treatment->id)
            ->where('is_active', true)
            ->value('duration_minutes');

        if ($branchDuration !== null) {
            return (int) $branchDuration;
        }

        return $treatment->duration_minutes !== null
            ? (int) $treatment->duration_minutes
            : null;
    }

    /**
     * Doktorun ilgili tarihte bu şubede aktif görevi var mı?
     */
    protected function doctorWorksAtBranch(
        int $doctorId,
        int $branchId,
        Carbon $date
    ): bool {
        return DB::table('doctor_branch')
            ->where('doctor_id', $doctorId)
            ->where('branch_id', $branchId)
            ->where('status', 'active')
            ->where(function ($query) use ($date) {
                $query
                    ->whereNull('start_date')
                    ->orWhereDate(
                        'start_date',
                        '<=',
                        $date->toDateString()
                    );
            })
            ->where(function ($query) use ($date) {
                $query
                    ->whereNull('end_date')
                    ->orWhereDate(
                        'end_date',
                        '>=',
                        $date->toDateString()
                    );
            })
            ->exists();
    }

    /**
     * Şube bu tedaviyi aktif olarak sunuyor mu?
     */
    protected function branchOffersTreatment(
        int $branchId,
        int $treatmentId
    ): bool {
        return DB::table('branch_treatment')
            ->where('branch_id', $branchId)
            ->where('treatment_id', $treatmentId)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Doktor bu tedaviyi aktif olarak yapıyor mu?
     */
    protected function doctorPerformsTreatment(
        int $doctorId,
        int $treatmentId
    ): bool {
        return DB::table('doctor_treatment')
            ->where('doctor_id', $doctorId)
            ->where('treatment_id', $treatmentId)
            ->where('is_active', true)
            ->exists();
    }

    protected function allowsOnlineBooking(
        int $branchId,
        int $treatmentId,
        Carbon $startsAt,
        Carbon $now,
        ?BranchAppointmentSetting $settings
    ): bool {
        $bookableTreatment = DB::table('branch_treatment')
            ->where('branch_id', $branchId)
            ->where('treatment_id', $treatmentId)
            ->where('is_active', true)
            ->where('is_online_bookable', true)
            ->exists();

        if (! $bookableTreatment) {
            return false;
        }

        if (! $settings) {
            return true;
        }

        return $settings->online_booking_enabled
            && ($settings->same_day_booking_enabled || ! $startsAt->isSameDay($now))
            && $startsAt->gte($now->copy()->addMinutes($settings->minimum_booking_notice_minutes))
            && $startsAt->lte($now->copy()->addDays($settings->maximum_booking_days)->endOfDay());
    }

    /**
     * Randevu, hazırlık ve temizlik süresiyle bir çalışma aralığına sığmalı.
     * Çevrimiçi seçimler çalışma aralığından hesaplanan başlangıç saatini kullanır.
     */
    protected function isWithinWorkingHours(
        int $doctorId,
        int $branchId,
        Carbon $occupiedStart,
        Carbon $occupiedEnd,
        Carbon $startsAt,
        ?int $slotIntervalMinutes,
    ): bool {
        if ($slotIntervalMinutes !== null && $slotIntervalMinutes <= 0) {
            return false;
        }

        $dayOfWeek = $occupiedStart->dayOfWeek;

        $workingHours = DB::table('doctor_working_hours')
            ->where('doctor_id', $doctorId)
            ->where('branch_id', $branchId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->get([
                'start_time',
                'end_time',
            ]);

        foreach ($workingHours as $workingHour) {
            $workStart = $occupiedStart->copy()->setTimeFromTimeString(
                $workingHour->start_time
            );

            $workEnd = $occupiedStart->copy()->setTimeFromTimeString(
                $workingHour->end_time
            );
            $matchesSlotInterval = $slotIntervalMinutes === null
                || ($startsAt->timestamp - $workStart->timestamp) % ($slotIntervalMinutes * 60) === 0;

            if (
                $occupiedStart->greaterThanOrEqualTo($workStart) &&
                $occupiedEnd->lessThanOrEqualTo($workEnd) &&
                $matchesSlotInterval
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Doktorun izin tarihi randevu aralığıyla kesişiyor mu?
     */
    protected function isOnLeave(
        int $doctorId,
        int $branchId,
        Carbon $startsAt,
        Carbon $endsAt
    ): bool {
        return DB::table('doctor_leaves')
            ->where('doctor_id', $doctorId)
            ->where('is_approved', true)
            ->where(function ($query) use ($branchId) {
                $query
                    ->whereNull('branch_id')
                    ->orWhere('branch_id', $branchId);
            })
            ->whereDate(
                'start_date',
                '<=',
                $endsAt->toDateString()
            )
            ->whereDate(
                'end_date',
                '>=',
                $startsAt->toDateString()
            )
            ->exists();
    }

    /**
     * Doktorun aktif takvim bloğuyla çakışma var mı?
     */
    protected function hasCalendarBlock(
        int $branchId,
        int $doctorId,
        Carbon $startsAt,
        Carbon $endsAt
    ): bool {
        return CalendarBlock::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->where(function ($query) use ($doctorId) {
                $query
                    ->whereNull('doctor_id')
                    ->orWhere('doctor_id', $doctorId);
            })
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();
    }

    /**
     * Doktorun aynı zaman aralığında başka aktif randevusu var mı?
     */
    protected function hasAppointmentConflict(
        int $doctorId,
        Carbon $startsAt,
        Carbon $endsAt,
        ?int $ignoreAppointmentId = null
    ): bool {
        return Appointment::query()
            ->leftJoin('branch_appointment_settings as booking_settings', fn ($join) => $join
                ->on('booking_settings.branch_id', '=', 'appointments.branch_id')
                ->where('booking_settings.is_active', true))
            ->where('appointments.doctor_id', $doctorId)
            ->whereNotIn('appointments.status', [
                'cancelled',
                'completed',
                'rescheduled',
                'no_show',
            ])
            ->when(
                $ignoreAppointmentId !== null,
                fn ($query) => $query->where(
                    'appointments.id',
                    '!=',
                    $ignoreAppointmentId
                )
            )
            // Yalnızca şube ayarlarının genişletebileceği randevuları getir.
            ->where('appointments.starts_at', '<', $endsAt->copy()->addMinutes(BranchAppointmentSetting::MAX_BUFFER_MINUTES))
            ->where('appointments.ends_at', '>', $startsAt->copy()->subMinutes(BranchAppointmentSetting::MAX_BUFFER_MINUTES))
            ->get(['appointments.starts_at', 'appointments.ends_at', 'booking_settings.buffer_before_minutes', 'booking_settings.buffer_after_minutes'])
            ->contains(fn (Appointment $appointment): bool => $appointment->starts_at->copy()->subMinutes((int) $appointment->buffer_before_minutes)->lt($endsAt)
                && $appointment->ends_at->copy()->addMinutes((int) $appointment->buffer_after_minutes)->gt($startsAt));
    }
}
