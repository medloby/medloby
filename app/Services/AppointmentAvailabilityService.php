<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\CalendarBlock;
use App\Models\Doctor;
use App\Models\Treatment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AppointmentAvailabilityService
{
    public function __construct(
        protected BookingCalendarService $bookingCalendarService
    ) {
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
        bool $requireOnlineBookable = true
    ): bool {
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

        // 4. Medloby üzerinden online randevu ise
        // online booking şartını kontrol et.
        if (
            $requireOnlineBookable &&
            ! $this->isOnlineBookable(
                $branchId,
                $treatment->id
            )
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

        // 6. Minimum randevu öncesi bildirim süresini kontrol et.
        if (
            $requireOnlineBookable &&
            ! $this->meetsMinimumBookingNotice(
                $branchId,
                $startsAt
            )
        ) {
            return false;
        }

        // 7. Doktorun çalışma saatleri uygun mu?
        if (! $this->isWithinWorkingHours(
            $doctor->id,
            $branchId,
            $startsAt,
            $endsAt
        )) {
            return false;
        }

        // 8. Doktor izinli mi?
        if ($this->isOnLeave(
            $doctor->id,
            $branchId,
            $startsAt,
            $endsAt
        )) {
            return false;
        }

        // 9. Manuel takvim bloğu var mı?
        if ($this->hasCalendarBlock(
            $branchId,
            $doctor->id,
            $startsAt,
            $endsAt
        )) {
            return false;
        }

        // 10. Başka aktif randevuyla çakışıyor mu?
        if ($this->hasAppointmentConflict(
            $doctor->id,
            $branchId,
            $startsAt,
            $endsAt,
            $ignoreAppointmentId
        )) {
            return false;
        }

        return true;
    }

    /**
     * Klinik tarafından belirlenen minimum randevu öncesi
     * bildirim süresini kontrol eder.
     */
    protected function meetsMinimumBookingNotice(
        int $branchId,
        Carbon $startsAt
    ): bool {
        $minimumNotice = DB::table('branch_appointment_settings')
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->value('minimum_booking_notice_minutes');

        /*
         * Şube ayarı yoksa herhangi bir minimum süre
         * uygulanmaz.
         */
        if ($minimumNotice === null) {
            return true;
        }

        $minimumNotice = (int) $minimumNotice;

        /*
         * 0 dakika = minimum bekleme yok.
         */
        if ($minimumNotice <= 0) {
            return true;
        }

        $earliestAllowedStart = now()->addMinutes(
            $minimumNotice
        );

        return $startsAt->greaterThanOrEqualTo(
            $earliestAllowedStart
        );
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

    /**
     * Şube + tedavi kombinasyonu online randevuya açık mı?
     */
    protected function isOnlineBookable(
        int $branchId,
        int $treatmentId
    ): bool {
        $branchTreatment = DB::table('branch_treatment')
            ->where('branch_id', $branchId)
            ->where('treatment_id', $treatmentId)
            ->where('is_active', true)
            ->first([
                'is_online_bookable',
            ]);

        if (! $branchTreatment) {
            return false;
        }

        return (bool) $branchTreatment->is_online_bookable;
    }

    /**
     * Randevu başlangıç-bitiş aralığı doktorun çalışma
     * saatlerinden birinin tamamen içinde mi?
     */
    protected function isWithinWorkingHours(
        int $doctorId,
        int $branchId,
        Carbon $startsAt,
        Carbon $endsAt
    ): bool {
        $dayOfWeek = $startsAt->dayOfWeek;

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
            $workStart = $startsAt->copy()->setTimeFromTimeString(
                $workingHour->start_time
            );

            $workEnd = $startsAt->copy()->setTimeFromTimeString(
                $workingHour->end_time
            );

            if (
                $startsAt->greaterThanOrEqualTo($workStart) &&
                $endsAt->lessThanOrEqualTo($workEnd)
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
        int $branchId,
        Carbon $startsAt,
        Carbon $endsAt,
        ?int $ignoreAppointmentId = null
    ): bool {
        return Appointment::query()
            ->where('doctor_id', $doctorId)
            ->where('branch_id', $branchId)
            ->whereNotIn('status', [
                'cancelled',
                'completed',
                'rescheduled',
                'no_show',
            ])
            ->when(
                $ignoreAppointmentId !== null,
                fn ($query) => $query->where(
                    'id',
                    '!=',
                    $ignoreAppointmentId
                )
            )
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();
    }
}