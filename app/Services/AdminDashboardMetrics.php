<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Offer;
use App\Models\PatientProfile;
use App\Models\PlatformContract;
use App\Models\SupportTicket;
use App\Models\Treatment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

final class AdminDashboardMetrics
{
    public function overview(): array
    {
        $now = CarbonImmutable::now('Europe/Istanbul');
        $today = $now->startOfDay();
        $tomorrow = $today->addDay();
        $firstDay = $today->subDays(29);
        $week = $today->startOfWeek(CarbonImmutable::MONDAY);
        $month = $today->startOfMonth();
        $clinics = Business::query()->where('type', 'clinic');
        $appointments = Appointment::query()->whereIn('status', ClinicAppointmentPerformance::BOOKED_STATES);
        $clinicStatuses = $this->distribution(clone $clinics, 'status', [
            'pending' => 'Bekliyor', 'active' => 'Aktif', 'suspended' => 'Askıda', 'rejected' => 'Reddedildi',
        ]);
        $plans = $this->distribution(clone $clinics, 'membership_plan', Business::MEMBERSHIP_PLANS, 'Plan seçilmemiş');
        $support = SupportTicket::query()->toBase()
            ->selectRaw('COUNT(*) AS total, COUNT(CASE WHEN closed_at IS NULL THEN 1 END) AS open')->first();
        $periodVisits = $this->periodCounts(clone $appointments, 'starts_at', [
            'today' => [$today, $tomorrow],
            'week' => [$week, $week->addWeek()],
            'month' => [$month, $month->addMonth()],
        ], false);
        $newUsers = $this->periodCounts(User::query(), 'created_at', [
            'today' => [$today, $tomorrow],
            'month' => [$month, $tomorrow],
        ], true);
        $openOffers = Offer::query()->where('status', 'pending');
        $awaitingExpiry = (clone $openOffers)->where('valid_until', '<', $now->utc()->startOfSecond())->count();
        $pendingOffers = $openOffers->where(fn (Builder $offer) => $offer->whereNull('valid_until')
            ->orWhere('valid_until', '>=', $now->utc()->startOfSecond()))->count();
        $openTickets = (int) $support->open;
        $membershipContracts = PlatformContract::query()->effectiveClinicMembership();
        $membershipExpiry = (clone $membershipContracts)->whereNotNull('expires_at')->orderBy('expires_at')->first(['expires_at']);

        return [
            'as_of' => $now->toIso8601String(),
            'trend_starts_on' => $firstDay->toDateString(),
            'trend_ends_on' => $today->toDateString(),
            'summary' => [
                'clinics' => array_sum(array_column($clinicStatuses, 'count')),
                'active_clinics' => $clinicStatuses['active']['count'],
                'suspended_clinics' => $clinicStatuses['suspended']['count'],
                'pending_applications' => $clinicStatuses['pending']['count'],
                'patient_accounts' => PatientProfile::query()->count(),
                'new_users' => $newUsers,
                'appointments' => $periodVisits,
                'pending_offers' => $pendingOffers,
                'open_tickets' => $openTickets,
                'active_branches' => Branch::query()->where('status', 'active')
                    ->whereHas('business', fn (Builder $business) => $business->where('type', 'clinic')
                        ->where('status', 'active')->where('is_verified', true))->count(),
            ],
            'clinic_statuses' => array_values($clinicStatuses),
            'appointment_statuses' => array_values($this->distribution(
                (clone $appointments)->where('starts_at', '>=', $firstDay->toDateTimeString())
                    ->where('starts_at', '<', $tomorrow->toDateTimeString()),
                'status',
                ['pending' => 'Onay bekliyor', 'confirmed' => 'Onaylandı', 'completed' => 'Tamamlandı',
                    'cancelled' => 'İptal edildi', 'no_show' => 'Hasta gelmedi'],
            )),
            'plans' => array_values($plans),
            'support' => [
                ['key' => 'open', 'label' => 'Açık', 'count' => $openTickets],
                ['key' => 'closed', 'label' => 'Kapalı', 'count' => (int) $support->total - $openTickets],
            ],
            'trends' => [
                'clinics' => $this->dailyTrend($clinics, 'created_at', $firstDay, $tomorrow, true),
                'appointments' => $this->dailyTrend($appointments, 'starts_at', $firstDay, $tomorrow, false),
            ],
            'alerts' => [
                'membership_contract_missing' => ! $membershipContracts->exists(),
                'membership_contract_expires_at' => $membershipExpiry?->expires_at?->toIso8601String(),
                'offers_awaiting_expiry' => $awaitingExpiry,
                'failed_jobs' => $this->failedJobs(),
                'active_treatments_in_inactive_categories' => Treatment::query()->where('is_active', true)
                    ->whereHas('treatmentCategory', fn (Builder $categories) => $categories->where('is_active', false))->count(),
            ],
        ];
    }

    public function analytics(): array
    {
        $today = CarbonImmutable::today('Europe/Istanbul');
        $from = $today->subDays(29);
        $until = $today->addDay();
        $clinics = Business::query()->where('type', 'clinic');
        $offers = Offer::query()->where('created_at', '>=', $from->utc()->toDateTimeString())
            ->where('created_at', '<', $until->utc()->toDateTimeString());
        $outcomes = $this->distribution(clone $offers, 'status', [
            'pending' => 'Yanıt bekliyor', 'accepted' => 'Kabul edildi',
            'rejected' => 'Reddedildi', 'expired' => 'Süresi doldu',
        ]);
        $accepted = (clone $offers)->where('status', 'accepted');
        $converted = (clone $accepted)->whereHas('appointments', fn (Builder $visits) => $visits
            ->whereColumn('appointments.business_id', 'offers.business_id')
            ->whereColumn('appointments.patient_profile_id', 'offers.patient_profile_id')
            ->whereIn('appointments.status', ClinicAppointmentPerformance::BOOKED_STATES))->count();
        $activeClinicDays = Appointment::query()->whereIn('status', ClinicAppointmentPerformance::BOOKED_STATES)
            ->where('starts_at', '>=', $from->toDateTimeString())->where('starts_at', '<', $until->toDateTimeString())
            ->whereHas('business', fn (Builder $business) => $business->where('type', 'clinic'));
        $cities = $clinics->toBase()->select(['city', 'country_code'])->selectRaw('COUNT(*) AS tally')
            ->groupBy('city', 'country_code')->orderByDesc('tally')->orderBy('country_code')->orderBy('city')->limit(12)->get();

        return [
            'starts_on' => $from->toDateString(),
            'ends_on' => $today->toDateString(),
            'offers_created' => array_sum(array_column($outcomes, 'count')),
            'offers_converted' => $converted,
            'clinics_with_appointments' => $activeClinicDays->distinct()->count('business_id'),
            'outcomes' => array_values($outcomes),
            'cities' => $cities->map(fn (object $row): array => [
                'label' => (blank($row->city) ? 'İl belirtilmemiş' : $row->city).' · '.$row->country_code,
                'count' => (int) $row->tally,
            ])->all(),
        ];
    }

    private function distribution(Builder $query, string $column, array $labels, string $missing = 'Diğer'): array
    {
        $counts = $query->toBase()->select($column)->selectRaw('COUNT(*) AS tally')->groupBy($column)->pluck('tally', $column);
        $rows = [];
        foreach ($labels as $key => $label) {
            $rows[$key] = ['key' => $key, 'label' => $label, 'count' => (int) $counts->pull($key, 0)];
        }
        if ($counts->sum() > 0) {
            $rows['other'] = ['key' => 'other', 'label' => $missing, 'count' => (int) $counts->sum()];
        }

        return $rows;
    }

    private function dailyTrend(Builder $query, string $column, CarbonImmutable $from, CarbonImmutable $until, bool $utc): array
    {
        $days = [];
        for ($day = $from; $day < $until; $day = $day->addDay()) {
            $days[$day->toDateString()] = [$day, $day->addDay()];
        }
        $counts = $this->periodCounts($query, $column, $days, $utc);

        return array_map(fn (string $date): array => ['date' => $date, 'count' => $counts[$date]], array_keys($days));
    }

    /** @param array<string, array{CarbonImmutable, CarbonImmutable}> $periods */
    private function periodCounts(Builder $query, string $column, array $periods, bool $utc): array
    {
        $expressions = [];
        $starts = [];
        $ends = [];
        $bindings = [];
        foreach (array_values($periods) as $index => [$start, $end]) {
            $from = ($utc ? $start->utc() : $start)->toDateTimeString();
            $until = ($utc ? $end->utc() : $end)->toDateTimeString();
            $expressions[] = "COUNT(CASE WHEN {$column} >= ? AND {$column} < ? THEN 1 END) AS period_{$index}";
            $starts[] = $from;
            $ends[] = $until;
            array_push($bindings, $from, $until);
        }
        $row = $query->toBase()->where($column, '>=', min($starts))->where($column, '<', max($ends))
            ->selectRaw(implode(', ', $expressions), $bindings)->first();
        $counts = [];
        foreach (array_keys($periods) as $index => $key) {
            $counts[$key] = (int) $row->{'period_'.$index};
        }

        return $counts;
    }

    private function failedJobs(): ?int
    {
        $settings = config('queue.failed', []);
        if (! in_array($settings['driver'] ?? null, ['database', 'database-uuids'], true)) {
            return null;
        }

        try {
            return DB::connection($settings['database'] ?? null)->table($settings['table'])->count();
        } catch (Throwable) {
            return null;
        }
    }
}
