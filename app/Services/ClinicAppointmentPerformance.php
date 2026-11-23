<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\CalendarBlock;
use App\Models\DoctorLeave;
use App\Models\DoctorWorkingHour;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ClinicAppointmentPerformance
{
    public const array BOOKED_STATES = ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'];

    public function summarize(int $businessId, array $branches, CarbonImmutable $month): array
    {
        $start = $month->startOfMonth();
        $end = $month->endOfMonth();
        $firstDay = $start->toDateString();
        $lastDay = $end->toDateString();
        $assignments = DB::table('doctor_branch')
            ->join('doctors', 'doctors.id', '=', 'doctor_branch.doctor_id')
            ->join('people', 'people.id', '=', 'doctors.person_id')
            ->where('people.business_id', $businessId)->where('doctors.status', 'active')->where('people.status', 'active')
            ->whereIn('doctor_branch.branch_id', $branches)->where('doctor_branch.status', 'active')
            ->where(fn ($query) => $query->whereNull('doctor_branch.start_date')->orWhereDate('doctor_branch.start_date', '<=', $lastDay))
            ->where(fn ($query) => $query->whereNull('doctor_branch.end_date')->orWhereDate('doctor_branch.end_date', '>=', $firstDay))
            ->get(['doctor_branch.doctor_id', 'doctor_branch.branch_id', 'doctor_branch.start_date', 'doctor_branch.end_date',
                'people.first_name', 'people.last_name']);
        $doctorIds = $assignments->pluck('doctor_id')->unique()->all();
        $hours = DoctorWorkingHour::query()->whereIn('branch_id', $branches)->whereIn('doctor_id', $doctorIds)
            ->where('is_active', true)->get()->groupBy(['branch_id', 'doctor_id', 'day_of_week']);
        $leave = DoctorLeave::query()->whereIn('doctor_id', $doctorIds)->where('is_approved', true)
            ->whereDate('start_date', '<=', $lastDay)->whereDate('end_date', '>=', $firstDay)
            ->where(fn (Builder $query) => $query->whereNull('branch_id')->orWhereIn('branch_id', $branches))
            ->get()->groupBy('doctor_id');
        $blocks = CalendarBlock::query()->where('business_id', $businessId)->whereIn('branch_id', $branches)
            ->where('is_active', true)->where('starts_at', '<', $end->addSecond()->toDateTimeString())
            ->where('ends_at', '>', $start->toDateTimeString())->get()->groupBy('branch_id');
        $visits = Appointment::query()->where('business_id', $businessId)->whereIn('branch_id', $branches)
            ->whereIn('status', self::BOOKED_STATES)
            ->whereBetween('starts_at', [$start->toDateTimeString(), $end->format('Y-m-d H:i:s.u')])
            ->get(['branch_id', 'doctor_id', 'status', 'starts_at', 'ends_at']);
        $branchCounts = $visits->groupBy('branch_id')->map(fn (Collection $records): array => $this->counts($records))->all();
        $doctorVisits = $visits->whereNotNull('doctor_id')->groupBy(['branch_id', 'doctor_id']);

        $doctors = $assignments->map(function (object $assignment) use ($hours, $leave, $blocks, $doctorVisits, $start, $end): array {
            $branchId = $assignment->branch_id;
            $doctorId = $assignment->doctor_id;
            $shifts = $hours->get($branchId)?->get($doctorId) ?? collect();
            $leaves = $leave->get($doctorId, collect())->filter(fn (DoctorLeave $row) => $row->branch_id === null || $row->branch_id === $branchId);
            $closures = $blocks->get($branchId, collect())->filter(fn (CalendarBlock $row) => $row->doctor_id === null || $row->doctor_id === $doctorId);
            $records = $doctorVisits->get($branchId)?->get($doctorId) ?? collect();
            $bookedDays = $records->whereIn('status', ['pending', 'confirmed', 'completed'])->groupBy(fn (Appointment $visit) => $visit->starts_at->toDateString());
            $capacity = $booked = 0;

            for ($day = $start; $day <= $end; $day = $day->addDay()) {
                $date = $day->toDateString();
                if (($assignment->start_date !== null && $date < substr($assignment->start_date, 0, 10))
                    || ($assignment->end_date !== null && $date > substr($assignment->end_date, 0, 10))
                    || $leaves->contains(fn (DoctorLeave $row) => $row->start_date->toDateString() <= $date && $row->end_date->toDateString() >= $date)) {
                    continue;
                }
                $working = $this->merged($shifts->get($day->dayOfWeek, collect())->map(fn (DoctorWorkingHour $hour): array => [
                    $this->seconds($hour->start_time), $this->seconds($hour->end_time),
                ])->all());
                $blocked = $this->intervalsOn($closures, $date);
                $occupied = $this->intervalsOn($bookedDays->get($date, collect()), $date);
                $available = $this->subtract($working, $blocked);
                $capacity += $this->duration($available);
                foreach ($available as [$from, $to]) {
                    foreach ($occupied as [$appointmentStart, $appointmentEnd]) {
                        $booked += max(0, min($to, $appointmentEnd) - max($from, $appointmentStart));
                    }
                }
            }

            return [
                'branch_id' => $branchId, 'doctor_id' => $doctorId, 'name' => trim($assignment->first_name.' '.$assignment->last_name),
                'available_minutes' => round($capacity / 60, 2), 'booked_minutes' => round($booked / 60, 2),
                'occupancy_percent' => $this->percent($booked, $capacity),
                ...$this->counts($records),
            ];
        })->sortBy('name')->values()->all();

        return ['counts' => $this->counts($visits), 'branches' => $branchCounts, 'doctors' => $doctors];
    }

    public function counts(Collection $visits): array
    {
        $counts = $visits->countBy('status');
        $total = $visits->count();
        $cancelled = $counts->get('cancelled', 0);
        $noShow = $counts->get('no_show', 0);
        $completed = $counts->get('completed', 0);

        return [
            'total' => $total, 'completed' => $completed, 'cancelled' => $cancelled, 'no_show' => $noShow,
            'cancellation_percent' => $this->percent($cancelled, $total),
            'no_show_percent' => $this->percent($noShow, $completed + $noShow),
        ];
    }

    private function percent(int $number, int $base): ?float
    {
        return $base > 0 ? round($number * 100 / $base, 1) : null;
    }

    private function seconds(string $time): int
    {
        [$hours, $minutes, $seconds] = array_pad(explode(':', $time), 3, '0');

        return (int) $hours * 3600 + (int) $minutes * 60 + (int) $seconds;
    }

    private function intervalsOn(Collection $records, string $date): array
    {
        return $this->merged($records->filter(fn ($row) => $row->starts_at->toDateString() <= $date && $row->ends_at->toDateString() >= $date)
            ->map(fn ($row): array => [
                $row->starts_at->toDateString() < $date ? 0 : $this->seconds($row->starts_at->format('H:i:s')),
                $row->ends_at->toDateString() > $date ? 86400 : $this->seconds($row->ends_at->format('H:i:s')),
            ])->all());
    }

    private function merged(array $intervals): array
    {
        sort($intervals);
        $merged = [];
        foreach ($intervals as [$start, $end]) {
            if ($start >= $end) {
                continue;
            }
            $last = array_key_last($merged);
            if ($last !== null && $start <= $merged[$last][1]) {
                $merged[$last][1] = max($merged[$last][1], $end);
            } else {
                $merged[] = [$start, $end];
            }
        }

        return $merged;
    }

    private function subtract(array $working, array $blocked): array
    {
        $result = [];
        foreach ($working as [$start, $end]) {
            foreach ($blocked as [$from, $to]) {
                if ($from >= $end) {
                    break;
                }
                if ($to <= $start) {
                    continue;
                }
                if ($from > $start) {
                    $result[] = [$start, $from];
                }
                $start = min($end, $to);
            }
            if ($start < $end) {
                $result[] = [$start, $end];
            }
        }

        return $result;
    }

    private function duration(array $intervals): int
    {
        return array_sum(array_map(fn (array $range): int => $range[1] - $range[0], $intervals));
    }
}
