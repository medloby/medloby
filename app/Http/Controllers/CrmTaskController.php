<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BusinessUser;
use App\Models\CrmLead;
use App\Models\CrmTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CrmTaskController extends Controller
{
    public function index(
        Request $request,
        Branch $branch
    ): JsonResponse {
        $businessUser = $this->resolveAuthorizedBusinessUser(
            $branch->business_id,
            $branch->id
        );

        if (! $businessUser) {
            return $this->forbiddenResponse();
        }

        $query = CrmTask::query()
            ->where('business_id', $branch->business_id)
            ->where('branch_id', $branch->id)
            ->with([
                'lead',
                'assignedBusinessUser',
                'createdByBusinessUser',
            ])
            ->latest();

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->string('status')->toString()
            );
        }

        if ($request->filled('priority')) {
            $query->where(
                'priority',
                $request->string('priority')->toString()
            );
        }

        if ($request->filled('type')) {
            $query->where(
                'type',
                $request->string('type')->toString()
            );
        }

        if ($request->filled('assigned_business_user_id')) {
            $query->where(
                'assigned_business_user_id',
                $request->integer('assigned_business_user_id')
            );
        }

        if ($request->filled('crm_lead_id')) {
            $query->where(
                'crm_lead_id',
                $request->integer('crm_lead_id')
            );
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    public function store(
        Request $request,
        Branch $branch
    ): JsonResponse {
        $createdByBusinessUser = $this->resolveAuthorizedBusinessUser(
            $branch->business_id,
            $branch->id
        );

        if (! $createdByBusinessUser) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'crm_lead_id' => [
                'nullable',
                'integer',
                'exists:crm_leads,id',
            ],
            'assigned_business_user_id' => [
                'nullable',
                'integer',
                'exists:business_user,id',
            ],
            'title' => [
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'type' => [
                'sometimes',
                'string',
                Rule::in([
                    'call',
                    'whatsapp',
                    'email',
                    'meeting',
                    'follow_up',
                    'other',
                ]),
            ],
            'status' => [
                'sometimes',
                'string',
                Rule::in([
                    'pending',
                    'in_progress',
                    'completed',
                    'cancelled',
                ]),
            ],
            'priority' => [
                'sometimes',
                'string',
                Rule::in([
                    'low',
                    'normal',
                    'high',
                    'urgent',
                ]),
            ],
            'due_at' => [
                'nullable',
                'date',
            ],
            'started_at' => [
                'nullable',
                'date',
            ],
            'completed_at' => [
                'nullable',
                'date',
            ],
        ]);

        $lead = null;

        if (
            isset($validated['crm_lead_id'])
            && $validated['crm_lead_id'] !== null
        ) {
            $lead = CrmLead::query()
                ->where('id', $validated['crm_lead_id'])
                ->where('business_id', $branch->business_id)
                ->where('branch_id', $branch->id)
                ->first();

            if (! $lead) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seçilen lead bu şubeye ait değil.',
                ], 422);
            }
        }

        $assignedBusinessUser = null;

        if (
            isset($validated['assigned_business_user_id'])
            && $validated['assigned_business_user_id'] !== null
        ) {
            $assignedBusinessUser = $this->resolveBusinessUserForBranch(
                $validated['assigned_business_user_id'],
                $branch->business_id,
                $branch->id
            );

            if (! $assignedBusinessUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seçilen personel bu işletmeye ait değil, aktif değil veya bu şubeye erişimi yok.',
                ], 422);
            }
        }

        $status = $validated['status'] ?? 'pending';

        $startedAt = $validated['started_at'] ?? null;
        $completedAt = $validated['completed_at'] ?? null;

        if ($status === 'in_progress' && $startedAt === null) {
            $startedAt = now();
        }

        if ($status === 'completed' && $completedAt === null) {
            $completedAt = now();
        }

        $task = CrmTask::create([
            'business_id' => $branch->business_id,
            'branch_id' => $branch->id,
            'crm_lead_id' => $lead?->id,
            'assigned_business_user_id' =>
                $assignedBusinessUser?->id,
            'created_by_business_user_id' =>
                $createdByBusinessUser->id,
            'title' => $validated['title'],
            'description' =>
                $validated['description'] ?? null,
            'type' =>
                $validated['type'] ?? 'other',
            'status' => $status,
            'priority' =>
                $validated['priority'] ?? 'normal',
            'due_at' =>
                $validated['due_at'] ?? null,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
        ]);

        return response()->json([
            'success' => true,
            'data' => $task->fresh([
                'lead',
                'assignedBusinessUser',
                'createdByBusinessUser',
            ]),
        ], 201);
    }

    public function show(
        Branch $branch,
        CrmTask $crmTask
    ): JsonResponse {
        $businessUser = $this->resolveAuthorizedBusinessUser(
            $branch->business_id,
            $branch->id
        );

        if (! $businessUser) {
            return $this->forbiddenResponse();
        }

        if (
            $crmTask->business_id !== $branch->business_id
            || $crmTask->branch_id !== $branch->id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Bu görev belirtilen şubeye ait değil.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $crmTask->load([
                'lead',
                'assignedBusinessUser',
                'createdByBusinessUser',
                'branch',
                'business',
            ]),
        ]);
    }

    public function update(
        Request $request,
        Branch $branch,
        CrmTask $crmTask
    ): JsonResponse {
        $businessUser = $this->resolveAuthorizedBusinessUser(
            $branch->business_id,
            $branch->id
        );

        if (! $businessUser) {
            return $this->forbiddenResponse();
        }

        if (
            $crmTask->business_id !== $branch->business_id
            || $crmTask->branch_id !== $branch->id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Bu görev belirtilen şubeye ait değil.',
            ], 404);
        }

        $validated = $request->validate([
            'crm_lead_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:crm_leads,id',
            ],
            'assigned_business_user_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:business_user,id',
            ],
            'title' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'description' => [
                'sometimes',
                'nullable',
                'string',
            ],
            'type' => [
                'sometimes',
                'string',
                Rule::in([
                    'call',
                    'whatsapp',
                    'email',
                    'meeting',
                    'follow_up',
                    'other',
                ]),
            ],
            'status' => [
                'sometimes',
                'string',
                Rule::in([
                    'pending',
                    'in_progress',
                    'completed',
                    'cancelled',
                ]),
            ],
            'priority' => [
                'sometimes',
                'string',
                Rule::in([
                    'low',
                    'normal',
                    'high',
                    'urgent',
                ]),
            ],
            'due_at' => [
                'sometimes',
                'nullable',
                'date',
            ],
            'started_at' => [
                'sometimes',
                'nullable',
                'date',
            ],
            'completed_at' => [
                'sometimes',
                'nullable',
                'date',
            ],
        ]);

        if (
            array_key_exists('crm_lead_id', $validated)
            && $validated['crm_lead_id'] !== null
        ) {
            $lead = CrmLead::query()
                ->where('id', $validated['crm_lead_id'])
                ->where('business_id', $branch->business_id)
                ->where('branch_id', $branch->id)
                ->first();

            if (! $lead) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seçilen lead bu şubeye ait değil.',
                ], 422);
            }
        }

        if (
            array_key_exists(
                'assigned_business_user_id',
                $validated
            )
            && $validated['assigned_business_user_id'] !== null
        ) {
            $assignedBusinessUser = $this->resolveBusinessUserForBranch(
                $validated['assigned_business_user_id'],
                $branch->business_id,
                $branch->id
            );

            if (! $assignedBusinessUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seçilen personel bu işletmeye ait değil, aktif değil veya bu şubeye erişimi yok.',
                ], 422);
            }
        }

        if (
            array_key_exists('status', $validated)
            && $validated['status'] === 'in_progress'
            && $crmTask->started_at === null
            && ! array_key_exists('started_at', $validated)
        ) {
            $validated['started_at'] = now();
        }

        if (
            array_key_exists('status', $validated)
            && $validated['status'] === 'completed'
            && $crmTask->completed_at === null
            && ! array_key_exists('completed_at', $validated)
        ) {
            $validated['completed_at'] = now();
        }

        $crmTask->update($validated);

        return response()->json([
            'success' => true,
            'data' => $crmTask->fresh([
                'lead',
                'assignedBusinessUser',
                'createdByBusinessUser',
            ]),
        ]);
    }

    public function complete(
        Branch $branch,
        CrmTask $crmTask
    ): JsonResponse {
        $businessUser = $this->resolveAuthorizedBusinessUser(
            $branch->business_id,
            $branch->id
        );

        if (! $businessUser) {
            return $this->forbiddenResponse();
        }

        if (
            $crmTask->business_id !== $branch->business_id
            || $crmTask->branch_id !== $branch->id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Bu görev belirtilen şubeye ait değil.',
            ], 404);
        }

        if ($crmTask->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Bu görev zaten tamamlanmış.',
            ], 422);
        }

        $crmTask->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $crmTask->fresh([
                'lead',
                'assignedBusinessUser',
                'createdByBusinessUser',
            ]),
        ]);
    }

    public function cancel(
        Branch $branch,
        CrmTask $crmTask
    ): JsonResponse {
        $businessUser = $this->resolveAuthorizedBusinessUser(
            $branch->business_id,
            $branch->id
        );

        if (! $businessUser) {
            return $this->forbiddenResponse();
        }

        if (
            $crmTask->business_id !== $branch->business_id
            || $crmTask->branch_id !== $branch->id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Bu görev belirtilen şubeye ait değil.',
            ], 404);
        }

        if ($crmTask->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Tamamlanmış görev iptal edilemez.',
            ], 422);
        }

        if ($crmTask->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Bu görev zaten iptal edilmiş.',
            ], 422);
        }

        $crmTask->update([
            'status' => 'cancelled',
        ]);

        return response()->json([
            'success' => true,
            'data' => $crmTask->fresh([
                'lead',
                'assignedBusinessUser',
                'createdByBusinessUser',
            ]),
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

    private function resolveBusinessUserForBranch(
        int $businessUserId,
        int $businessId,
        int $branchId
    ): ?BusinessUser {
        return BusinessUser::query()
            ->where('id', $businessUserId)
            ->where('business_id', $businessId)
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