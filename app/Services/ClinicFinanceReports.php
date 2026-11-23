<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchLedgerEntry;
use App\Models\Doctor;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection as Rows;

final class ClinicFinanceReports
{
    /** @param Collection<int, Branch> $branches */
    public function build(int $businessId, Collection $branches, ?CarbonImmutable $start, ?CarbonImmutable $end, bool $showMonths): array
    {
        $branchIds = $branches->modelKeys();
        $cash = $this->cash($businessId, $branchIds, $start, $end);
        $income = $cash->whereNotNull('doctor_id')->where('income', '>', 0)->groupBy('doctor_id');
        $visits = $this->doctorVisits($businessId, $branchIds, $start, $end)->groupBy('doctor_id');
        $branchTotals = $cash->groupBy('branch_id');
        $monthTotals = $showMonths ? $cash->groupBy('month')->map(fn (Rows $rows, string $month): array => [
            'month' => $month, 'totals' => $this->totalCash($rows),
        ])->values()->all() : [];

        return [
            'totals' => $this->totalCash($cash, includeUnattributed: true),
            'branches' => $branches->map(fn (Branch $branch): array => [
                ...$branch->only(['id', 'name']),
                'totals' => $this->totalCash($branchTotals->get($branch->id, collect())),
            ])->all(),
            'months' => $monthTotals,
            'doctors' => $this->doctors($businessId, $visits, $income),
        ];
    }

    private function totalCash(Rows $rows, bool $includeUnattributed = false): array
    {
        return $rows->groupBy('currency')->sortKeys()->map(function (Rows $currencyRows, string $currency) use ($includeUnattributed): array {
            $totals = BranchLedgerEntry::formatTotals($currency, (int) $currencyRows->sum('income'), (int) $currencyRows->sum('expense'));
            if ($includeUnattributed) {
                $totals['unattributed_income'] = BranchLedgerEntry::formatAmount((int) $currencyRows->whereNull('doctor_id')->sum('income'));
            }

            return $totals;
        })->values()->all();
    }

    /** @param list<int> $branchIds */
    private function cash(int $businessId, array $branchIds, ?CarbonImmutable $start, ?CarbonImmutable $end): Collection
    {
        $entries = BranchLedgerEntry::query()->whereIn('branch_ledger_entries.branch_id', $branchIds)->whereNull('voided_at')
            ->leftJoin('appointments', function (JoinClause $join) use ($businessId): void {
                $join->on('appointments.id', '=', 'branch_ledger_entries.appointment_id')
                    ->on('appointments.branch_id', '=', 'branch_ledger_entries.branch_id')->where('appointments.business_id', $businessId);
            })
            ->leftJoin('doctors', 'doctors.id', '=', 'appointments.doctor_id')
            ->leftJoin('people', fn (JoinClause $join) => $join->on('people.id', '=', 'doctors.person_id')->where('people.business_id', $businessId));
        if ($start !== null && $end !== null) {
            $entries->recordedBetween($start, $end);
        }
        $doctor = 'CASE WHEN people.id IS NOT NULL THEN appointments.doctor_id END';

        return $entries->selectRaw('SUBSTR(CAST(entry_date AS TEXT), 1, 7) AS month')
            ->selectRaw($doctor.' AS doctor_id')->groupByRaw($doctor)->groupBy('month')
            ->withAmountTotals(['branch_ledger_entries.branch_id', 'branch_ledger_entries.currency'])->orderBy('month')->get();
    }

    /** @param list<int> $branchIds */
    private function doctorVisits(int $businessId, array $branchIds, ?CarbonImmutable $start, ?CarbonImmutable $end): Collection
    {
        $visits = Appointment::query()->where('appointments.business_id', $businessId)->whereIn('appointments.branch_id', $branchIds)
            ->whereIn('appointments.status', ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'])
            ->join('doctors', 'doctors.id', '=', 'appointments.doctor_id')
            ->join('people', 'people.id', '=', 'doctors.person_id')->where('people.business_id', $businessId)
            ->leftJoin('treatments', 'treatments.id', '=', 'appointments.treatment_id');
        if ($start !== null && $end !== null) {
            $visits->whereBetween('appointments.starts_at', [$start->toDateTimeString(), $end->format('Y-m-d H:i:s.u')]);
        }

        return $visits->select(['appointments.doctor_id', 'appointments.treatment_id', 'treatments.name AS treatment'])
            ->selectRaw("COUNT(*) AS total,
                COUNT(CASE WHEN appointments.status = 'completed' THEN 1 END) AS completed,
                COUNT(CASE WHEN appointments.status IN ('pending', 'confirmed') THEN 1 END) AS scheduled,
                COUNT(CASE WHEN appointments.status = 'cancelled' THEN 1 END) AS cancelled,
                COUNT(CASE WHEN appointments.status = 'no_show' THEN 1 END) AS no_show")
            ->groupBy('appointments.doctor_id', 'appointments.treatment_id', 'treatments.name')
            ->orderBy('treatments.name')->orderBy('appointments.treatment_id')->get();
    }

    private function doctors(int $businessId, Collection $visits, Collection $income): array
    {
        $doctorIds = $visits->keys()->merge($income->keys())->unique()->values();

        return Doctor::query()->whereKey($doctorIds)->whereHas('person', fn (Builder $people) => $people->where('business_id', $businessId))
            ->with('person:id,title,first_name,last_name')->get(['id', 'person_id'])->map(function (Doctor $doctor) use ($visits, $income): array {
                $person = $doctor->person;
                $treatments = $visits->get($doctor->id, collect());

                return [
                    'id' => $doctor->id,
                    'name' => implode(' ', array_filter([$person->title, $person->first_name, $person->last_name])),
                    'total' => (int) $treatments->sum('total'),
                    'completed' => (int) $treatments->sum('completed'),
                    'scheduled' => (int) $treatments->sum('scheduled'),
                    'cancelled' => (int) $treatments->sum('cancelled'),
                    'no_show' => (int) $treatments->sum('no_show'),
                    'treatments' => $treatments->map(fn (Appointment $row): array => [
                        'name' => $row->treatment,
                        'total' => (int) $row->total,
                        'completed' => (int) $row->completed,
                    ])->all(),
                    'income' => $income->get($doctor->id, collect())->groupBy('currency')->sortKeys()->map(fn (Rows $rows, string $currency): array => [
                        'currency' => $currency,
                        'amount' => BranchLedgerEntry::formatAmount((int) $rows->sum('income')),
                    ])->values()->all(),
                ];
            })->sortBy('name')->values()->all();
    }
}
