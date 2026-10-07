<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BusinessUser;
use App\Models\CrmActivity;
use App\Models\CrmLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CrmActivityController extends Controller
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

        $query = CrmActivity::query()
            ->where('business_id', $branch->business_id)
            ->where('branch_id', $branch->id)
            ->with([
                'lead',
                'businessUser',
            ])
            ->latest('occurred_at');

        if ($request->filled('crm_lead_id')) {
            $query->where(
                'crm_lead_id',
                $request->integer('crm_lead_id')
            );
        }

        if ($request->filled('business_user_id')) {
            $query->where(
                'business_user_id',
                $request->integer('business_user_id')
            );
        }

        if ($request->filled('type')) {
            $query->where(
                'type',
                $request->string('type')->toString()
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
            'type' => [
                'required',
                'string',
                Rule::in([
                    'call',
                    'whatsapp',
                    'email',
                    'meeting',
                    'note',
                    'follow_up',
                    'other',
                ]),
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
            'occurred_at' => [
                'nullable',
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

        $activity = CrmActivity::create([
            'business_id' => $branch->business_id,
            'branch_id' => $branch->id,
            'crm_lead_id' => $lead->id,
            'business_user_id' => $businessUser->id,
            'type' => $validated['type'],
            'title' => $validated['title'],
            'description' =>
                $validated['description'] ?? null,
            'occurred_at' =>
                $validated['occurred_at'] ?? now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $activity->fresh([
                'lead',
                'businessUser',
            ]),
        ], 201);
    }

    public function show(
        Branch $branch,
        CrmActivity $crmActivity
    ): JsonResponse {
        $businessUser = $this->resolveAuthorizedBusinessUser(
            $branch->business_id,
            $branch->id
        );

        if (! $businessUser) {
            return $this->forbiddenResponse();
        }

        if (
            $crmActivity->business_id !== $branch->business_id
            || $crmActivity->branch_id !== $branch->id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Bu aktivite belirtilen şubeye ait değil.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $crmActivity->load([
                'lead',
                'businessUser',
                'branch',
                'business',
            ]),
        ]);
    }

    public function update(
        Request $request,
        Branch $branch,
        CrmActivity $crmActivity
    ): JsonResponse {
        $businessUser = $this->resolveAuthorizedBusinessUser(
            $branch->business_id,
            $branch->id
        );

        if (! $businessUser) {
            return $this->forbiddenResponse();
        }

        if (
            $crmActivity->business_id !== $branch->business_id
            || $crmActivity->branch_id !== $branch->id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Bu aktivite belirtilen şubeye ait değil.',
            ], 404);
        }

        $validated = $request->validate([
            'crm_lead_id' => [
                'sometimes',
                'integer',
                'exists:crm_leads,id',
            ],
            'type' => [
                'sometimes',
                'string',
                Rule::in([
                    'call',
                    'whatsapp',
                    'email',
                    'meeting',
                    'note',
                    'follow_up',
                    'other',
                ]),
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
            'occurred_at' => [
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
                    'message' => 'Seçilen lead bu şubeye ait değil.',
                ], 422);
            }
        }

        $crmActivity->update($validated);

        return response()->json([
            'success' => true,
            'data' => $crmActivity->fresh([
                'lead',
                'businessUser',
            ]),
        ]);
    }

    public function destroy(
        Branch $branch,
        CrmActivity $crmActivity
    ): JsonResponse {
        $businessUser = $this->resolveAuthorizedBusinessUser(
            $branch->business_id,
            $branch->id
        );

        if (! $businessUser) {
            return $this->forbiddenResponse();
        }

        if (
            $crmActivity->business_id !== $branch->business_id
            || $crmActivity->branch_id !== $branch->id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Bu aktivite belirtilen şubeye ait değil.',
            ], 404);
        }

        $crmActivity->delete();

        return response()->json([
            'success' => true,
            'message' => 'CRM aktivitesi silindi.',
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