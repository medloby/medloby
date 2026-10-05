<?php

namespace App\Services;

use App\Models\BookingCalendarOverride;
use App\Models\BookingCalendarRule;
use Carbon\Carbon;

class BookingCalendarService
{
    /**
     * Belirli bir şubenin belirli bir tarihte
     * online randevu kabul edip etmediğini kontrol eder.
     *
     * Öncelik sırası:
     *
     * 1. Aktif override
     * 2. Şubenin varsayılan takvim kuralı
     * 3. Güvenli varsayılan: açık
     */
    public function isDateOpen(
        int $branchId,
        Carbon $date
    ): bool {
        $date = $date->copy()->startOfDay();

        $override = BookingCalendarOverride::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->orderByDesc('id')
            ->first();

        if ($override) {
            return $override->status === 'open';
        }

        $rule = BookingCalendarRule::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->first();

        if (! $rule) {
            return true;
        }

        return $rule->default_status === 'open';
    }

    /**
     * Belirli bir tarih aralığında en az bir açık gün var mı?
     */
    public function hasOpenDateInRange(
        int $branchId,
        Carbon $startDate,
        Carbon $endDate
    ): bool {
        $startDate = $startDate->copy()->startOfDay();
        $endDate = $endDate->copy()->startOfDay();

        if ($endDate->lessThan($startDate)) {
            return false;
        }

        $currentDate = $startDate->copy();

        while ($currentDate->lessThanOrEqualTo($endDate)) {
            if ($this->isDateOpen($branchId, $currentDate)) {
                return true;
            }

            $currentDate->addDay();
        }

        return false;
    }

    /**
     * Belirli bir tarih aralığındaki açık günleri döndürür.
     */
    public function getOpenDatesInRange(
        int $branchId,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        $startDate = $startDate->copy()->startOfDay();
        $endDate = $endDate->copy()->startOfDay();

        if ($endDate->lessThan($startDate)) {
            return [];
        }

        $openDates = [];

        $currentDate = $startDate->copy();

        while ($currentDate->lessThanOrEqualTo($endDate)) {
            if ($this->isDateOpen($branchId, $currentDate)) {
                $openDates[] = $currentDate->toDateString();
            }

            $currentDate->addDay();
        }

        return $openDates;
    }

    /**
     * Belirli bir tarih için aktif override kaydını döndürür.
     */
    public function getOverride(
        int $branchId,
        Carbon $date
    ): ?BookingCalendarOverride {
        return BookingCalendarOverride::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Şubenin aktif takvim kuralını döndürür.
     */
    public function getRule(
        int $branchId
    ): ?BookingCalendarRule {
        return BookingCalendarRule::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->first();
    }
}