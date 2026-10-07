<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BusinessUser;
use App\Models\CrmActivity;
use App\Models\CrmFollowUp;
use App\Models\CrmLead;
use App\Models\CrmPipelineStage;
use App\Models\CrmTask;
use Illuminate\Http\JsonResponse;

class CrmDashboardController extends Controller
{
    public function index(
        Branch $branch
    ): JsonResponse {
        $businessUser = $this->resolveAuthorizedBusinessUser(
            $branch->business_id,
            $branch->id
        );

        if (! $businessUser) {
            return $this->forbiddenResponse();
        }

        $baseLeadQuery = CrmLead::query()
            ->where('business_id', $branch->business_id)
            ->where('branch_id', $branch->id);

        $totalLeads = (clone $baseLeadQuery)->count();

        $newLeads = (clone $baseLeadQuery)
            ->where('status', 'new')
            ->count();

        $convertedLeads = (clone $baseLeadQuery)
            ->where('status', 'converted')
            ->count();

        $lostLeads = (clone $baseLeadQuery)
            ->where('status', 'lost')
            ->count();

        /*
         * CRM pipeline stages are business-level.
         * Lead ownership remains branch-level.
         */
        $pipelineStages = CrmPipelineStage::query()
            ->where('business_id', $branch->business_id)
            ->orderBy('sort_order')
            ->get([
                'id',
                'name',
                'slug',
                'color',
                'sort_order',
                'is_active',
            ]);

        $pipeline = $pipelineStages->map(function (
            CrmPipelineStage $stage
        ) use ($branch) {
            $count = CrmLead::query()
                ->where('business_id', $branch->business_id)
                ->where('branch_id', $branch->id)
                ->where('pipeline_stage_id', $stage->id)
                ->count();

            return [
                'id' => $stage->id,
                'name' => $stage->name,
                'slug' => $stage->slug,
                'color' => $stage->color,
                'sort_order' => $stage->sort_order,
                'is_active' => $stage->is_active,
                'lead_count' => $count,
            ];
        })->values();

        $pendingFollowUps = CrmFollowUp::query()
            ->where('business_id', $branch->business_id)
            ->where('branch_id', $branch->id)
            ->where('status', 'pending')
            ->count();

        $todayFollowUps = CrmFollowUp::query()
            ->where('business_id', $branch->business_id)
            ->where('branch_id', $branch->id)
            ->where('status', 'pending')
            ->whereDate('scheduled_at', today())
            ->count();

        $completedFollowUps = CrmFollowUp::query()
            ->where('business_id', $branch->business_id)
            ->where('branch_id', $branch->id)
            ->where('status', 'completed')
            ->count();

        $openTasks = CrmTask::query()
            ->where('business_id', $branch->business_id)
            ->where('branch_id', $branch->id)
            ->whereNotIn('status', [
                'completed',
                'cancelled',
            ])
            ->count();

        $completedTasks = CrmTask::query()
            ->where('business_id', $branch->business_id)
            ->where('branch_id', $branch->id)
            ->where('status', 'completed')
            ->count();

        $todayActivities = CrmActivity::query()
            ->where('business_id', $branch->business_id)
            ->where('branch_id', $branch->id)
            ->whereDate('occurred_at', today())
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

        $staffPerformance = BusinessUser::query()
            ->where('business_id', $branch->business_id)
            ->where('is_active', true)
            ->whereHas('branches', function ($query) use ($branch) {
                $query
                    ->where('branches.id', $branch->id)
                    ->where(
                        'business_user_branch.is_active',
                        true
                    );
            })
            ->get()
            ->map(function (BusinessUser $staff) use ($branch) {
                $leadCount = CrmLead::query()
                    ->where('business_id', $branch->business_id)
                    ->where('branch_id', $branch->id)
                    ->where(
                        'assigned_business_user_id',
                        $staff->id
                    )
                    ->count();

                $taskCount = CrmTask::query()
                    ->where('business_id', $branch->business_id)
                    ->where('branch_id', $branch->id)
                    ->where(
                        'assigned_business_user_id',
                        $staff->id
                    )
                    ->count();

                $completedTaskCount = CrmTask::query()
                    ->where('business_id', $branch->business_id)
                    ->where('branch_id', $branch->id)
                    ->where(
                        'assigned_business_user_id',
                        $staff->id
                    )
                    ->where('status', 'completed')
                    ->count();

                $followUpCount = CrmFollowUp::query()
                    ->where('business_id', $branch->business_id)
                    ->where('branch_id', $branch->id)
                    ->where(
                        'assigned_business_user_id',
                        $staff->id
                    )
                    ->where('status', 'pending')
                    ->count();

                return [
                    'business_user_id' => $staff->id,
                    'user_id' => $staff->user_id,
                    'role' => $staff->role,
                    'lead_count' => $leadCount,
                    'task_count' => $taskCount,
                    'completed_task_count' =>
                        $completedTaskCount,
                    'pending_follow_up_count' =>
                        $followUpCount,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
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
        ]);
    }

    private function resolveAuthorizedBusinessUser(
        int $businessId,
        int $branchId
    ): ?BusinessUser {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        return BusinessUser::query()
            ->where('business_id', $businessId)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereHas('branches', function ($query) use ($branchId) {
                $query
                    ->where('branches.id', $branchId)
                    ->where(
                        'business_user_branch.is_active',
                        true
                    );
            })
            ->first();
    }

    private function forbiddenResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Bu şubeye erişim yetkiniz yok.',
        ], 403);
    }
}