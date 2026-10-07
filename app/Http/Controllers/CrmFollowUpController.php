<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BusinessUser;
use App\Models\CrmFollowUp;
use App\Models\CrmLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CrmFollowUpController extends Controller
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

        $query = CrmFollowUp::query()
            ->where('business_id', $branch->business_id)
            ->where('branch_id', $branch->id)
            ->with([
                'lead',
                'assignedBusinessUser',
            ])
            ->orderBy('scheduled_at');

        if ($request->filled('crm_lead_id')) {
            $query->where(
                'crm_lead_id',
                $request->integer('crm_lead_id')
            );
        }

        if ($request->filled('assigned_business_user_id')) {
            $query->where(
                'assigned_business_user_id',
                $request->integer('assigned_business_user_id')
            );
        }

        if ($request->filled('type')) {
            $query->where(
                'type',
                $request->string('type')->toString()
            );
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->string('status')->toString()
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
        $businessUser = $this->resolveAuthorizedBusinessUser(
            $branch->business_id,
            $branch->id
        );

        if (! $businessUser) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'crm_lead_id' => [
                'required',
                'integer',
                'exists:crm_leads,id',
            ],
            'assigned_business_user_id' => [
                'nullable',
                'integer',
                'exists:business_user,id',
            ],
            'type' => [
                'required',
                'string',
                Rule::in([
                    'call',
                    'whatsapp',
                    'email',
                    'meeting',
                    'other',
                ]),
            ],
            'title' => [
                'required',
                'string',
                'max:255',
            ],
            'notes' => [
                'nullable',
                'string',
            ],
            'scheduled_at' => [
                'required',
                'date',
            ],
        ]);

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

        $assignedBusinessUserId =
            $validated['assigned_business_user_id'] ?? null;

        if ($assignedBusinessUserId !== null) {
            $assignedBusinessUser = $this->resolveBusinessUserForBranch(
                $branch->business_id,
                $branch->id,
                $assignedBusinessUserId
            );

            if (! $assignedBusinessUser) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'Seçilen personelin bu şubeye aktif erişimi yok.',
                ], 422);
            }
        }

        $followUp = CrmFollowUp::create([
            'business_id' => $branch->business_id,
            'branch_id' => $branch->id,
            'crm_lead_id' => $lead->id,
            'assigned_business_user_id' =>
                $assignedBusinessUserId,
            'type' => $validated['type'],
            'title' => $validated['title'],
            'notes' => $validated['notes'] ?? null,
            'scheduled_at' => $validated['scheduled_at'],
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'data' => $followUp->fresh([
                'lead',
                'assignedBusinessUser',
            ]),
        ], 201);
    }

    public function show(
        Branch $branch,
        CrmFollowUp $crmFollowUp
    ): JsonResponse {
        $businessUser = $this->resolveAuthorizedBusinessUser(
            $branch->business_id,
            $branch->id
        );

        if (! $businessUser) {
            return $this->forbiddenResponse();
        }

        if (
            $crmFollowUp->business_id !== $branch->business_id
            || $crmFollowUp->branch_id !== $branch->id
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Bu takip belirtilen şubeye ait değil.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $crmFollowUp->load([
                'lead',
                'assignedBusinessUser',
                'branch',
                'business',
            ]),
        ]);
    }

    public function update(
        Request $request,
        Branch $branch,
        CrmFollowUp $crmFollowUp
    ): JsonResponse {
        $businessUser = $this->resolveAuthorizedBusinessUser(
            $branch->business_id,
            $branch->id
        );

        if (! $businessUser) {
            return $this->forbiddenResponse();
        }

        if (
            $crmFollowUp->business_id !== $branch->business_id
            || $crmFollowUp->branch_id !== $branch->id
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Bu takip belirtilen şubeye ait değil.',
            ], 404);
        }

        $validated = $request->validate([
            'crm_lead_id' => [
                'sometimes',
                'integer',
                'exists:crm_leads,id',
            ],
            'assigned_business_user_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:business_user,id',
            ],
            'type' => [
                'sometimes',
                'string',
                Rule::in([
                    'call',
                    'whatsapp',
                    'email',
                    'meeting',
                    'other',
                ]),
            ],
            'title' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'notes' => [
                'sometimes',
                'nullable',
                'string',
            ],
            'scheduled_at' => [
                'sometimes',
                'date',
            ],
        ]);

        if (
            array_key_exists('crm_lead_id', $validated)
        ) {
            $lead = CrmLead::query()
                ->where('id', $validated['crm_lead_id'])
                ->where('business_id', $branch->business_id)
                ->where('branch_id', $branch->id)
                ->first();

            if (! $lead) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'Seçilen lead bu şubeye ait değil.',
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
            $assignedBusinessUser =
                $this->resolveBusinessUserForBranch(
                    $branch->business_id,
                    $branch->id,
                    $validated['assigned_business_user_id']
                );

            if (! $assignedBusinessUser) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'Seçilen personelin bu şubeye aktif erişimi yok.',
                ], 422);
            }
        }

        $crmFollowUp->update($validated);

        return response()->json([
            'success' => true,
            'data' => $crmFollowUp->fresh([
                'lead',
                'assignedBusinessUser',
            ]),
        ]);
    }

    public function complete(
        Branch $branch,
        CrmFollowUp $crmFollowUp
    ): JsonResponse {
        $businessUser = $this->resolveAuthorizedBusinessUser(
            $branch->business_id,
            $branch->id
        );

        if (! $businessUser) {
            return $this->forbiddenResponse();
        }

        if (
            $crmFollowUp->business_id !== $branch->business_id
            || $crmFollowUp->branch_id !== $branch->id
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Bu takip belirtilen şubeye ait değil.',
            ], 404);
        }

        if ($crmFollowUp->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' =>
                    'Sadece bekleyen takipler tamamlanabilir.',
            ], 422);
        }

        $crmFollowUp->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $crmFollowUp->fresh([
                'lead',
                'assignedBusinessUser',
            ]),
        ]);
    }

    public function cancel(
        Branch $branch,
        CrmFollowUp $crmFollowUp
    ): JsonResponse {
        $businessUser = $this->resolveAuthorizedBusinessUser(
            $branch->business_id,
            $branch->id
        );

        if (! $businessUser) {
            return $this->forbiddenResponse();
        }

        if (
            $crmFollowUp->business_id !== $branch->business_id
            || $crmFollowUp->branch_id !== $branch->id
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Bu takip belirtilen şubeye ait değil.',
            ], 404);
        }

        if ($crmFollowUp->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' =>
                    'Sadece bekleyen takipler iptal edilebilir.',
            ], 422);
        }

        $crmFollowUp->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $crmFollowUp->fresh([
                'lead',
                'assignedBusinessUser',
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
        int $businessId,
        int $branchId,
        int $businessUserId
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