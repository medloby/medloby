<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\Treatment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AppointmentSlotAvailabilityService
{
    public function __construct(
        protected AppointmentAvailabilityService $availabilityService
    ) {
    }

    /**
     * Belirli bir gün için doktorun müsait randevu başlangıç saatlerini üretir.
     *
     * Slot aralığı teknik başlangıç aralığıdır.
     * Tedavinin süresi klinik/şube/doktor tarafından belirlenen
     * duration_minutes değerinden alınır.
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
        int $slotIntervalMinutes = 30,
        bool $requireOnlineBookable = true
    ): array {
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

            // Geçersiz veya boş çalışma aralığını atla.
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
                            $doctor,
                            $branchId,
                            $treatment,
                            $slot,
                            null,
                            $requireOnlineBookable
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

        return $slots;
    }
}