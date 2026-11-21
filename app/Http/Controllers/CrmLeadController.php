<?php

namespace App\Http\Controllers;

use App\Http\Resources\CrmLeadResource;
use App\Models\Branch;
use App\Models\BusinessUser;
use App\Models\CrmLead;
use App\Models\CrmPipelineStage;
use App\Models\CrmPipelineStageHistory;
use App\Models\PatientProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CrmLeadController extends Controller
{
    public function index(
        Request $request,
        Branch $branch
    ): JsonResponse {
        $filters = $request->validate([
            'per_page' => ['sometimes', 'required', 'integer', 'min:1', 'max:100'],
        ]);

        $query = CrmLead::query()
            ->where('business_id', $branch->business_id)
            ->where('branch_id', $branch->id)
            ->latest()
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->string('status')->toString()
            );
        }

        if ($request->filled('source')) {
            $query->where(
                'source',
                $request->string('source')->toString()
            );
        }

        if ($request->filled('priority')) {
            $query->where(
                'priority',
                $request->string('priority')->toString()
            );
        }

        if ($request->filled('assigned_business_user_id')) {
            $query->whereIn('assigned_business_user_id', BusinessUser::query()
                ->activeForBranch($branch)
                ->whereKey($request->integer('assigned_business_user_id'))
                ->select('business_user.id'));
        }

        if ($request->filled('pipeline_stage_id')) {
            $query->where(
                'pipeline_stage_id',
                $request->integer('pipeline_stage_id')
            );
        }

        $leads = isset($filters['per_page'])
            ? $query->with(['pipelineStage' => fn ($stage) => $stage->select('id', 'name')
                ->where('business_id', $branch->business_id)])
                ->paginate((int) $filters['per_page'], ['id', 'first_name', 'last_name', 'phone', 'email', 'status', 'pipeline_stage_id'])
                ->withQueryString()
            : CrmLeadResource::collection($query->with($this->leadRelations($branch))->get());

        return response()->json([
            'success' => true,
            'data' => $leads,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(
        Request $request,
        Branch $branch
    ): JsonResponse {
        $validated = $request->validate([
            'patient_profile_id' => [
                'nullable',
                'integer',
            ],
            'assigned_business_user_id' => [
                'nullable',
                'integer',
            ],
            'pipeline_stage_id' => [
                'nullable',
                'integer',
                'exists:crm_pipeline_stages,id',
            ],
            'first_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'last_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
            ],
            'phone' => [
                'nullable',
                'string',
                'max:50',
            ],
            'country_code' => [
                'nullable',
                'string',
                'max:10',
            ],
            'source' => [
                'sometimes',
                'string',
                'max:50',
            ],
            'status' => [
                'sometimes',
                'string',
                'max:50',
            ],
            'priority' => [
                'sometimes',
                'string',
                'max:20',
            ],
            'notes' => [
                'nullable',
                'string',
            ],
            'last_contacted_at' => [
                'nullable',
                'date',
            ],
            'next_follow_up_at' => [
                'nullable',
                'date',
            ],
        ]);

        $pipelineStage = null;

        if (
            isset($validated['pipeline_stage_id'])
            && $validated['pipeline_stage_id'] !== null
        ) {
            $pipelineStage = CrmPipelineStage::query()
                ->where('id', $validated['pipeline_stage_id'])
                ->where('business_id', $branch->business_id)
                ->where('is_active', true)
                ->first();

            if (! $pipelineStage) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seçilen pipeline aşaması bu işletmeye ait değil veya aktif değil.',
                ], 422);
            }
        }

        $lead = DB::transaction(function () use (
            $validated,
            $branch,
            $pipelineStage
        ) {
            $this->validateLeadRelations($validated, $branch);

            $lead = CrmLead::create([
                'business_id' => $branch->business_id,
                'branch_id' => $branch->id,
                'patient_profile_id' =>
                    $validated['patient_profile_id'] ?? null,
                'assigned_business_user_id' =>
                    $validated['assigned_business_user_id'] ?? null,
                'pipeline_stage_id' =>
                    $pipelineStage?->id,
                'first_name' =>
                    $validated['first_name'] ?? null,
                'last_name' =>
                    $validated['last_name'] ?? null,
                'email' =>
                    $validated['email'] ?? null,
                'phone' =>
                    $validated['phone'] ?? null,
                'country_code' =>
                    $validated['country_code'] ?? null,
                'source' =>
                    $validated['source'] ?? 'manual',
                'status' =>
                    $validated['status'] ?? 'new',
                'priority' =>
                    $validated['priority'] ?? 'normal',
                'notes' =>
                    $validated['notes'] ?? null,
                'last_contacted_at' =>
                    $validated['last_contacted_at'] ?? null,
                'next_follow_up_at' =>
                    $validated['next_follow_up_at'] ?? null,
            ]);

            if ($pipelineStage) {
                CrmPipelineStageHistory::create([
                    'crm_lead_id' => $lead->id,
                    'from_stage_id' => null,
                    'to_stage_id' => $pipelineStage->id,
                    'changed_by_business_user_id' =>
                        $this->resolveBusinessUserId(
                            $branch->business_id
                        ),
                    'notes' => 'Lead oluşturuldu.',
                    'changed_at' => now(),
                ]);
            }

            return $lead;
        });

        return response()->json([
            'success' => true,
            'data' => new CrmLeadResource($lead->load($this->leadRelations($branch))),
        ], 201)->header('Cache-Control', 'private, no-store');
    }

    public function show(
        Branch $branch,
        CrmLead $crmLead
    ): JsonResponse {
        if ($crmLead->business_id !== $branch->business_id || $crmLead->branch_id !== $branch->id) {
            return response()->json([
                'success' => false,
                'message' => 'Bu lead belirtilen merkeze ait değil.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new CrmLeadResource($crmLead->load([
                ...$this->leadRelations($branch),
                'pipelineStageHistories.fromStage' => fn ($stage) => $stage->where('business_id', $branch->business_id),
                'pipelineStageHistories.toStage' => fn ($stage) => $stage->where('business_id', $branch->business_id),
                'pipelineStageHistories.changedByBusinessUser' => fn ($member) => $member->where('business_id', $branch->business_id),
            ])),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function update(
        Request $request,
        Branch $branch,
        CrmLead $crmLead
    ): JsonResponse {
        if ($crmLead->business_id !== $branch->business_id || $crmLead->branch_id !== $branch->id) {
            return response()->json([
                'success' => false,
                'message' => 'Bu lead belirtilen merkeze ait değil.',
            ], 404);
        }

        $validated = $request->validate([
            'patient_profile_id' => [
                'nullable',
                'integer',
            ],
            'assigned_business_user_id' => [
                'nullable',
                'integer',
            ],
            'pipeline_stage_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:crm_pipeline_stages,id',
            ],
            'pipeline_stage_note' => [
                'sometimes',
                'nullable',
                'string',
                'max:2000',
            ],
            'first_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'last_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
            ],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
            ],
            'country_code' => [
                'sometimes',
                'nullable',
                'string',
                'max:10',
            ],
            'source' => [
                'sometimes',
                'string',
                'max:50',
            ],
            'status' => [
                'sometimes',
                'string',
                'max:50',
            ],
            'priority' => [
                'sometimes',
                'string',
                'max:20',
            ],
            'notes' => [
                'sometimes',
                'nullable',
                'string',
            ],
            'last_contacted_at' => [
                'sometimes',
                'nullable',
                'date',
            ],
            'next_follow_up_at' => [
                'sometimes',
                'nullable',
                'date',
            ],
            'converted_at' => [
                'sometimes',
                'nullable',
                'date',
            ],
            'lost_at' => [
                'sometimes',
                'nullable',
                'date',
            ],
            'lost_reason' => [
                'sometimes',
                'nullable',
                'string',
            ],
        ]);

        $pipelineStageNote = $validated['pipeline_stage_note'] ?? null;
        unset($validated['pipeline_stage_note']);

        return DB::transaction(function () use ($crmLead, $validated, $pipelineStageNote, $branch): JsonResponse {
            $lead = CrmLead::query()->where('business_id', $branch->business_id)
                ->where('branch_id', $branch->id)->lockForUpdate()->findOrFail($crmLead->id);
            $this->validateLeadRelations($validated, $branch);

            if (array_key_exists('patient_profile_id', $validated)
                && (int) $validated['patient_profile_id'] !== (int) $lead->patient_profile_id
                && $lead->conversations()->where('business_id', $branch->business_id)
                    ->where('branch_id', $branch->id)->where('patient_profile_id', $lead->patient_profile_id)->exists()) {
                throw ValidationException::withMessages([
                    'patient_profile_id' => 'Bağlı bir görüşmesi olan adayın hastası değiştirilemez.',
                ]);
            }

            $oldStageId = $lead->pipeline_stage_id;
            $newStageId = array_key_exists('pipeline_stage_id', $validated)
                ? $validated['pipeline_stage_id']
                : $oldStageId;
            $stageChanged = $newStageId !== null && (int) $newStageId !== (int) $oldStageId;

            if ($stageChanged && ! CrmPipelineStage::query()->whereKey($newStageId)
                ->where('business_id', $branch->business_id)->where('is_active', true)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seçilen pipeline aşaması bu işletmeye ait değil veya aktif değil.',
                ], 422);
            }

            $lead->update($validated);

            if ($stageChanged) {
                CrmPipelineStageHistory::create([
                    'crm_lead_id' => $lead->id,
                    'from_stage_id' => $oldStageId,
                    'to_stage_id' => $newStageId,
                    'changed_by_business_user_id' => $this->resolveBusinessUserId($branch->business_id),
                    'notes' => $pipelineStageNote,
                    'changed_at' => now(),
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => new CrmLeadResource($lead->load($this->leadRelations($branch))),
            ])->header('Cache-Control', 'private, no-store');
        }, attempts: 3);
    }

    public function convert(
        Branch $branch,
        CrmLead $crmLead
    ): JsonResponse {
        if ($crmLead->business_id !== $branch->business_id || $crmLead->branch_id !== $branch->id) {
            return response()->json([
                'success' => false,
                'message' => 'Bu lead belirtilen merkeze ait değil.',
            ], 404);
        }

        $crmLead->update([
            'status' => 'completed',
            'converted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => new CrmLeadResource($crmLead->load($this->leadRelations($branch))),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function markLost(
        Request $request,
        Branch $branch,
        CrmLead $crmLead
    ): JsonResponse {
        if ($crmLead->business_id !== $branch->business_id || $crmLead->branch_id !== $branch->id) {
            return response()->json([
                'success' => false,
                'message' => 'Bu lead belirtilen merkeze ait değil.',
            ], 404);
        }

        $validated = $request->validate([
            'lost_reason' => [
                'required',
                'string',
                'max:1000',
            ],
        ]);

        $crmLead->update([
            'status' => 'lost',
            'lost_at' => now(),
            'lost_reason' => $validated['lost_reason'],
        ]);

        return response()->json([
            'success' => true,
            'data' => new CrmLeadResource($crmLead->load($this->leadRelations($branch))),
        ])->header('Cache-Control', 'private, no-store');
    }

    private function leadRelations(Branch $branch): array
    {
        return [
            'patientProfile' => fn ($patient) => $patient->knownToBranch($branch),
            'assignedBusinessUser' => fn ($member) => $member->activeForBranch($branch),
            'branch',
            'pipelineStage' => fn ($stage) => $stage->where('business_id', $branch->business_id),
        ];
    }

    private function validateLeadRelations(array $validated, Branch $branch): void
    {
        $errors = [];

        if (isset($validated['patient_profile_id']) && ! PatientProfile::query()
            ->knownToBranch($branch)->whereKey($validated['patient_profile_id'])->exists()) {
            $errors['patient_profile_id'] = 'Seçilen hasta bu şubede ilişkilendirilemiyor.';
        }

        if (isset($validated['assigned_business_user_id']) && ! BusinessUser::query()
            ->activeForBranch($branch)->whereKey($validated['assigned_business_user_id'])->exists()) {
            $errors['assigned_business_user_id'] = 'Seçilen personel bu şubede aktif ve yetkili olmalıdır.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function resolveBusinessUserId(
        int $businessId
    ): ?int {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        $businessUser = BusinessUser::query()
            ->where('business_id', $businessId)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        return $businessUser?->id;
    }
}
