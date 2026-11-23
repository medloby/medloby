<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchLedgerEntry;
use App\Models\Business;
use App\Models\CrmLead;
use App\Models\CrmTask;
use App\Services\ClinicAppointmentPerformance;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClinicManagementController extends Controller
{
    public function __invoke(Request $request, Business $business, ClinicAppointmentPerformance $performance): JsonResponse
    {
        abort_unless($business->allowsCrmAccess(), 403);
        $actor = $request->user();
        $branches = $business->branches()->where('status', 'active')
            ->whereIn('id', $actor->accessibleBranchIds($business->id))->orderBy('name')->orderBy('id')->get();
        abort_if($branches->isEmpty(), 403);

        $fields = $request->validate(['month' => ['sometimes', 'required', 'date_format:Y-m', 'regex:/\A(?!0000)\d{4}-\d{2}\z/']]);
        $month = CarbonImmutable::createFromFormat('!Y-m', $fields['month'] ?? CarbonImmutable::now('Europe/Istanbul')->format('Y-m'), 'Europe/Istanbul');
        $end = $month->endOfMonth();
        $branchIds = $branches->modelKeys();
        $financialBranches = $branches->filter(fn (Branch $branch) => $actor->branchFinancePermissions($branch->setRelation('business', $business)) !== [])->modelKeys();
        $cash = BranchLedgerEntry::query()->whereIn('branch_id', $financialBranches)->whereNull('voided_at')
            ->recordedBetween($month, $end)->withAmountTotals(['branch_id', 'currency'])->orderBy('currency')->get()->groupBy('branch_id');
        $visitMetrics = $performance->summarize($business->id, $branchIds, $month);
        $leads = $this->leadConversions($business->id, $branchIds, $month, $end)->keyBy('branch_id');
        $tasks = CrmTask::query()->where('business_id', $business->id)->whereIn('branch_id', $branchIds);
        $open = (clone $tasks)->whereNotIn('status', ['completed', 'cancelled'])->groupBy('branch_id')->selectRaw('branch_id, COUNT(*) AS total')->pluck('total', 'branch_id');
        $taskPage = $tasks->orderByRaw("CASE WHEN status IN ('completed', 'cancelled') THEN 1 ELSE 0 END")
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')->orderBy('due_at')->latest('id')
            ->paginate(15, ['id', 'branch_id', 'title', 'status', 'due_at'], 'task_page')
            ->through(fn (CrmTask $task): array => $task->only(['id', 'branch_id', 'title', 'status']) + ['due_at' => $task->due_at?->toIso8601String()]);

        $visitPage = Appointment::query()->where('business_id', $business->id)->whereIn('branch_id', $branchIds)
            ->whereIn('status', ClinicAppointmentPerformance::BOOKED_STATES)
            ->whereBetween('starts_at', [$month->toDateTimeString(), $end->format('Y-m-d H:i:s.u')])
            ->with([
                'doctor.person' => fn ($people) => $people->where('business_id', $business->id)->select('id', 'first_name', 'last_name'),
                'treatment:id,name',
            ])->orderBy('starts_at')->orderBy('id')->paginate(15, ['id', 'branch_id', 'doctor_id', 'treatment_id', 'status', 'starts_at'], 'visit_page')
            ->through(fn (Appointment $visit): array => [
                ...$visit->only(['id', 'branch_id', 'status']),
                'starts_at' => $visit->starts_at->toDateTimeString(),
                'doctor' => $visit->doctor?->person ? trim($visit->doctor->person->first_name.' '.$visit->doctor->person->last_name) : null,
                'treatment' => $visit->treatment?->name,
            ]);

        return response()->json(['success' => true, 'data' => [
            'month' => $month->format('Y-m'),
            'summary' => [
                'open_tasks' => (int) $open->sum(), 'lead_count' => (int) $leads->sum('lead_count'),
                'offered_count' => (int) $leads->sum('offered_count'), 'booked_count' => (int) $leads->sum('booked_count'),
                'finance_branch_count' => count($financialBranches),
                'cash' => $cash->flatten(1)->groupBy('currency')->sortKeys()->map(fn ($rows, string $currency): array => BranchLedgerEntry::formatTotals($currency, (int) $rows->sum('income'), (int) $rows->sum('expense')))->values()->all(),
            ],
            'branches' => $branches->map(fn (Branch $branch): array => [
                ...$branch->only(['id', 'name']),
                'appointments' => $visitMetrics['branches'][$branch->id] ?? $performance->counts(collect()),
                'open_tasks' => (int) $open->get($branch->id, 0),
                'lead_count' => (int) ($leads->get($branch->id)?->lead_count ?? 0),
                'offered_count' => (int) ($leads->get($branch->id)?->offered_count ?? 0),
                'booked_count' => (int) ($leads->get($branch->id)?->booked_count ?? 0),
                'cash' => in_array($branch->id, $financialBranches, true) ? $cash->get($branch->id, collect())->map->amountTotals()->all() : null,
            ])->all(),
            'performance' => $visitMetrics['counts'],
            'doctors' => $visitMetrics['doctors'],
            'appointments' => $visitPage,
            'tasks' => $taskPage,
        ]])->header('Cache-Control', 'private, no-store');
    }

    private function leadConversions(int $businessId, array $branchIds, CarbonImmutable $month, CarbonImmutable $end): Collection
    {
        return CrmLead::query()->where('crm_leads.business_id', $businessId)->whereIn('crm_leads.branch_id', $branchIds)
            ->whereBetween('crm_leads.created_at', [$month->utc()->toDateTimeString(), $end->utc()->format('Y-m-d H:i:s.u')])
            ->groupBy('crm_leads.branch_id')->selectRaw("crm_leads.branch_id, COUNT(*) AS lead_count,
                COUNT(CASE WHEN EXISTS (
                    SELECT 1 FROM conversations JOIN offers ON offers.conversation_id = conversations.id
                    WHERE conversations.crm_lead_id = crm_leads.id
                        AND conversations.business_id = crm_leads.business_id AND conversations.branch_id = crm_leads.branch_id
                        AND offers.business_id = crm_leads.business_id AND (offers.branch_id IS NULL OR offers.branch_id = crm_leads.branch_id)
                        AND offers.patient_profile_id = conversations.patient_profile_id
                        AND (crm_leads.patient_profile_id IS NULL OR crm_leads.patient_profile_id = conversations.patient_profile_id)
                        AND offers.status IN ('pending', 'accepted', 'rejected', 'expired') AND offers.created_at >= crm_leads.created_at
                ) THEN 1 END) AS offered_count,
                COUNT(CASE WHEN EXISTS (
                    SELECT 1 FROM appointments WHERE appointments.business_id = crm_leads.business_id
                        AND appointments.branch_id = crm_leads.branch_id AND appointments.patient_profile_id = crm_leads.patient_profile_id
                        AND appointments.created_at >= crm_leads.created_at
                        AND appointments.status IN ('pending', 'confirmed', 'completed', 'no_show', 'cancelled')
                ) THEN 1 END) AS booked_count")->get();
    }
}
