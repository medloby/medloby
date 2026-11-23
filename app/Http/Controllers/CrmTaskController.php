<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureClinicCrmAccess;
use App\Http\Resources\CrmWorkResource;
use App\Models\Branch;
use App\Models\BusinessUser;
use App\Models\CrmLead;
use App\Models\CrmTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CrmTaskController extends Controller
{
    public function index(
        Request $request,
        Branch $branch
    ): JsonResponse {
        $query = CrmTask::query()
            ->where('business_id', $branch->business_id)
            ->where('branch_id', $branch->id)
            ->with([
                'lead' => CrmWorkResource::leadSummary($branch),
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
            )->whereHas('lead', fn ($lead) => $lead->forBranch($branch));
        }

        return response()->json([
            'success' => true,
            'data' => CrmWorkResource::collection($query->get()),
        ]);
    }

    public function store(
        Request $request,
        Branch $branch
    ): JsonResponse {
        $createdByBusinessUser = $request->attributes->get(EnsureClinicCrmAccess::class);

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

        if (isset($validated['assigned_business_user_id'])) {
            $assignedBusinessUser = BusinessUser::query()->activeForBranch($branch)
                ->find($validated['assigned_business_user_id']);

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
            'assigned_business_user_id' => $assignedBusinessUser?->id,
            'created_by_business_user_id' => $createdByBusinessUser->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'] ?? 'other',
            'status' => $status,
            'priority' => $validated['priority'] ?? 'normal',
            'due_at' => $validated['due_at'] ?? null,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
        ]);

        return response()->json([
            'success' => true,
            'data' => new CrmWorkResource($task->fresh([
                'lead' => CrmWorkResource::leadSummary($branch),
                'assignedBusinessUser',
                'createdByBusinessUser',
            ])),
        ], 201);
    }

    public function show(
        Branch $branch,
        CrmTask $crmTask
    ): JsonResponse {
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
            'data' => new CrmWorkResource($crmTask->load([
                'lead' => CrmWorkResource::leadSummary($branch),
                'assignedBusinessUser',
                'createdByBusinessUser',
                'branch',
                'business',
            ])),
        ]);
    }

    public function update(
        Request $request,
        Branch $branch,
        CrmTask $crmTask
    ): JsonResponse {
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

        if (isset($validated['assigned_business_user_id']) && ! BusinessUser::query()->activeForBranch($branch)
            ->whereKey($validated['assigned_business_user_id'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Seçilen personel bu işletmeye ait değil, aktif değil veya bu şubeye erişimi yok.',
            ], 422);
        }

        return $this->saveTask($branch, $crmTask, $validated);
    }

    public function complete(
        Branch $branch,
        CrmTask $crmTask
    ): JsonResponse {
        return $this->saveTask($branch, $crmTask, ['status' => 'completed'], requireOpen: true);
    }

    public function cancel(
        Branch $branch,
        CrmTask $crmTask
    ): JsonResponse {
        return $this->saveTask($branch, $crmTask, ['status' => 'cancelled'], requireOpen: true);
    }

    private function saveTask(
        Branch $branch,
        CrmTask $crmTask,
        array $changes,
        bool $requireOpen = false
    ): JsonResponse {
        if (
            $crmTask->business_id !== $branch->business_id
            || $crmTask->branch_id !== $branch->id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Bu görev belirtilen şubeye ait değil.',
            ], 404);
        }

        return DB::transaction(function () use ($branch, $crmTask, $changes, $requireOpen): JsonResponse {
            $task = CrmTask::query()
                ->where('business_id', $branch->business_id)->where('branch_id', $branch->id)
                ->lockForUpdate()->findOrFail($crmTask->id);
            $previousStatus = $task->status;
            $task->fill($changes);

            if (in_array($previousStatus, ['completed', 'cancelled'], true)
                && ($requireOpen || $task->isDirty(['status', 'completed_at']))) {
                $message = match (true) {
                    ! $requireOpen => 'Sonuçlandırılmış görevin durumu veya tamamlanma zamanı değiştirilemez.',
                    $previousStatus === 'completed' && $task->status === 'cancelled' => 'Tamamlanmış görev iptal edilemez.',
                    $previousStatus === 'cancelled' && $task->status === 'completed' => 'İptal edilen görev tamamlanamaz.',
                    $previousStatus === 'completed' => 'Bu görev zaten tamamlanmış.',
                    default => 'Bu görev zaten iptal edilmiş.',
                };

                return response()->json(['success' => false, 'message' => $message], 422);
            }

            if (($changes['status'] ?? null) === 'in_progress' && ! array_key_exists('started_at', $changes)) {
                $task->started_at ??= now();
            }

            if ($task->status === 'completed' && $previousStatus !== 'completed') {
                $task->completed_at = $requireOpen ? now() : ($task->completed_at ?? now());
            }

            $task->save();

            return response()->json([
                'success' => true,
                'data' => new CrmWorkResource($task->fresh(['lead' => CrmWorkResource::leadSummary($branch), 'assignedBusinessUser', 'createdByBusinessUser'])),
            ]);
        }, attempts: 3);
    }
}
