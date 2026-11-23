<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\BranchAppointmentSetting;
use App\Models\Doctor;
use App\Models\Treatment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AppointmentSlotAvailabilityService
{
    public function __construct(
        protected AppointmentAvailabilityService $availabilityService
    ) {}

    /**
     * Belirli bir gün için doktorun müsait randevu başlangıç saatlerini üretir.
     *
     * Slot aralığı öncelikle şubenin randevu ayarlarından alınır.
     *
     * Şube ayarında kayıt yoksa metoda gönderilen
     * slotIntervalMinutes değeri kullanılır.
     *
     * Aynı gün içinde birden fazla çalışma aralığı desteklenir.
     *
     * Örnek:
     *
     * 09:00 - 12:00
     * 13:00 - 18:00
     *
     * 12:00 - 13:00 arasında slot oluşturulmaz.
     */
    public function getAvailableSlots(
        Doctor $doctor,
        int $branchId,
        Treatment $treatment,
        Carbon $date,
        ?int $slotIntervalMinutes = null,
        bool $requireOnlineBookable = true,
        ?int $ignoreAppointmentId = null,
        ?int $patientProfileId = null,
    ): array {
        $date = Appointment::calendarDateTime($date);
        $settings = BranchAppointmentSetting::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->first();

        /*
         * Şube randevu ayarında slot aralığı varsa
         * her zaman onu kullan.
         */
        if ($settings) {
            $slotIntervalMinutes = $settings->slot_interval_minutes;

            /*
             * Online randevu ayarı kapalıysa
             * online müsaitlik gösterilmez.
             */
            if ($requireOnlineBookable && ! $settings->online_booking_enabled) {
                return [];
            }
        }

        /*
         * Şube ayarı henüz oluşturulmamışsa
         * güvenli varsayılan olarak 30 dakika kullan.
         */
        $slotIntervalMinutes ??= BranchAppointmentSetting::DEFAULT_SLOT_INTERVAL_MINUTES;

        if ($slotIntervalMinutes <= 0) {
            throw new InvalidArgumentException(
                'Slot aralığı 0 veya daha küçük olamaz.'
            );
        }

        $dayOfWeek = $date->dayOfWeek;

        $workingHours = DB::table('doctor_working_hours')
            ->where('doctor_id', $doctor->id)
            ->where('branch_id', $branchId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get([
                'start_time',
                'end_time',
            ]);

        if ($workingHours->isEmpty()) {
            return [];
        }

        $slots = [];

        foreach ($workingHours as $workingHour) {
            $workStart = $date->copy()->setTimeFromTimeString(
                $workingHour->start_time
            );

            $workEnd = $date->copy()->setTimeFromTimeString(
                $workingHour->end_time
            );

            /*
             * Geçersiz veya boş çalışma aralığını atla.
             */
            if ($workEnd->lessThanOrEqualTo($workStart)) {
                continue;
            }

            $cursor = $workStart->copy();

            while ($cursor->lessThan($workEnd)) {
                $slot = $cursor->copy();

                /*
                 * Aynı saat birden fazla çalışma kaydından
                 * oluşmuşsa ikinci kez ekleme.
                 */
                $slotKey = $slot->format('Y-m-d H:i:s');

                if (! isset($slots[$slotKey])) {
                    if (
                        $this->availabilityService->isAvailable(
                            doctor: $doctor,
                            branchId: $branchId,
                            treatment: $treatment,
                            startsAt: $slot,
                            ignoreAppointmentId: $ignoreAppointmentId,
                            requireOnlineBookable: $requireOnlineBookable,
                            fallbackSlotIntervalMinutes: $slotIntervalMinutes,
                        )
                    ) {
                        $slots[$slotKey] = $slot;
                    }
                }

                $cursor->addMinutes($slotIntervalMinutes);
            }
        }

        /*
         * Anahtarları sıfırla ve kronolojik sırada döndür.
         */
        $slots = array_values($slots);

        usort(
            $slots,
            fn (Carbon $a, Carbon $b) => $a->timestamp <=> $b->timestamp
        );

        return $this->excludePatientConflicts($slots, $doctor, $branchId, $treatment, $patientProfileId, $ignoreAppointmentId);
    }

    /**
     * @param  list<Carbon>  $slots  Chronological slots returned by getAvailableSlots
     * @return list<Carbon>
     */
    public function excludePatientConflicts(
        array $slots,
        Doctor $doctor,
        int $branchId,
        Treatment $treatment,
        ?int $patientProfileId,
        ?int $ignoreAppointmentId = null,
    ): array {
        if ($patientProfileId === null || $slots === []) {
            return $slots;
        }

        $duration = $this->availabilityService->getDurationMinutes($doctor, $branchId, $treatment);
        if ($duration === null || $duration <= 0) {
            return [];
        }

        $occupied = Appointment::query()->useWritePdo()->where('patient_profile_id', $patientProfileId)
            ->scheduledOverlapping($slots[0], $slots[array_key_last($slots)]->copy()->addMinutes($duration), $ignoreAppointmentId)
            ->get(['starts_at', 'ends_at']);

        return array_values(array_filter($slots, function (Carbon $start) use ($duration, $occupied): bool {
            $end = $start->copy()->addMinutes($duration);

            return ! $occupied->contains(fn (Appointment $appointment): bool => $appointment->starts_at->lt($end)
                && $appointment->ends_at->gt($start));
        }));
    }
}
