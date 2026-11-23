<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BusinessUser;
use App\Models\CrmActivity;
use App\Models\CrmFollowUp;
use App\Models\CrmLead;
use App\Models\CrmPipelineStage;
use App\Models\CrmTask;
use App\Services\CatalogManagementAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmDashboardController extends Controller
{
    public function index(
        Branch $branch,
        Request $request,
        CatalogManagementAccess $catalogAccess,
    ): JsonResponse {
        $baseLeadQuery = CrmLead::query()
            ->where('business_id', $branch->business_id)
            ->where('branch_id', $branch->id);
        $baseFollowUpQuery = CrmFollowUp::query()
            ->where('business_id', $branch->business_id)
            ->where('branch_id', $branch->id);
        $baseTaskQuery = CrmTask::query()
            ->where('business_id', $branch->business_id)
            ->where('branch_id', $branch->id);

        $localDay = CarbonImmutable::today('Europe/Istanbul');
        $dayStart = $localDay->utc();
        $nextDayStart = $localDay->addDay()->utc();

        $totalLeads = (clone $baseLeadQuery)->count();

        $newLeads = (clone $baseLeadQuery)
            ->where('status', 'new')
            ->count();

        $convertedLeads = (clone $baseLeadQuery)
            ->withStatus(CrmLead::CONVERTED)
            ->count();

        $lostLeads = (clone $baseLeadQuery)
            ->withStatus(CrmLead::LOST)
            ->count();

        /*
         * CRM pipeline stages are business-level.
         * Lead ownership remains branch-level.
         */
        $pipeline = CrmPipelineStage::query()
            ->where('business_id', $branch->business_id)
            ->orderBy('sort_order')
            ->select([
                'id',
                'name',
                'slug',
                'color',
                'sort_order',
                'is_active',
            ])
            ->withCount(['leads as lead_count' => fn ($leads) => $leads
                ->where('business_id', $branch->business_id)->where('branch_id', $branch->id)])
            ->get();

        $pendingFollowUpQuery = (clone $baseFollowUpQuery)->where('status', 'pending');
        $pendingFollowUps = (clone $pendingFollowUpQuery)->count();

        $todayFollowUps = (clone $pendingFollowUpQuery)
            ->where('scheduled_at', '>=', $dayStart)
            ->where('scheduled_at', '<', $nextDayStart)
            ->count();

        $completedFollowUps = (clone $baseFollowUpQuery)
            ->where('status', 'completed')
            ->count();

        $openTasks = (clone $baseTaskQuery)
            ->whereNotIn('status', [
                'completed',
                'cancelled',
            ])
            ->count();

        $completedTasks = (clone $baseTaskQuery)
            ->where('status', 'completed')
            ->count();

        $todayActivities = CrmActivity::query()
            ->where('business_id', $branch->business_id)
            ->where('branch_id', $branch->id)
            ->where('occurred_at', '>=', $dayStart)
            ->where('occurred_at', '<', $nextDayStart)
            ->count();

        $leadSources = (clone $baseLeadQuery)
            ->selectRaw('source, COUNT(*) as lead_count')
            ->groupBy('source')
            ->orderByDesc('lead_count')
            ->get()
            ->map(fn ($item) => [
                'source' => $item->source,
                'lead_count' => (int) $item->lead_count,
            ])
            ->values();

        $leadCounts = $baseLeadQuery->toBase()->whereNotNull('assigned_business_user_id')
            ->groupBy('assigned_business_user_id')->selectRaw('assigned_business_user_id, COUNT(*) AS lead_count')
            ->pluck('lead_count', 'assigned_business_user_id');
        $taskCounts = $baseTaskQuery->toBase()->whereNotNull('assigned_business_user_id')
            ->groupBy('assigned_business_user_id')->selectRaw('assigned_business_user_id, COUNT(*) AS task_count')
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) AS completed_task_count', ['completed'])
            ->get()->keyBy('assigned_business_user_id');
        $followUpCounts = $pendingFollowUpQuery->toBase()->whereNotNull('assigned_business_user_id')
            ->groupBy('assigned_business_user_id')->selectRaw('assigned_business_user_id, COUNT(*) AS follow_up_count')
            ->pluck('follow_up_count', 'assigned_business_user_id');

        $staffPerformance = BusinessUser::query()->activeForBranch($branch)->orderBy('id')
            ->get(['id', 'user_id', 'role'])
            ->map(fn (BusinessUser $member) => [
                'business_user_id' => $member->id,
                'user_id' => $member->user_id,
                'role' => $member->role,
                'lead_count' => (int) ($leadCounts[$member->id] ?? 0),
                'task_count' => (int) ($taskCounts[$member->id]->task_count ?? 0),
                'completed_task_count' => (int) ($taskCounts[$member->id]->completed_task_count ?? 0),
                'pending_follow_up_count' => (int) ($followUpCounts[$member->id] ?? 0),
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'can_manage_branch_catalog' => $catalogAccess->canManage($request->user(), $branch->business_id, $branch->id),
                'can_view_finances' => $request->user()->branchFinancePermissions($branch) !== [],
                'summary' => [
                    'total_leads' => $totalLeads,
                    'new_leads' => $newLeads,
                    'converted_leads' => $convertedLeads,
                    'lost_leads' => $lostLeads,
                    'pending_follow_ups' => $pendingFollowUps,
                    'today_follow_ups' => $todayFollowUps,
                    'completed_follow_ups' => $completedFollowUps,
                    'open_tasks' => $openTasks,
                    'completed_tasks' => $completedTasks,
                    'today_activities' => $todayActivities,
                ],
                'pipeline' => $pipeline,
                'lead_sources' => $leadSources,
                'staff_performance' => $staffPerformance,
            ],
        ])->header('Cache-Control', 'private, no-store');
    }
}
