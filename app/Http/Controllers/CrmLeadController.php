<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CrmLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CrmLeadController extends Controller
{
    public function index(
        Request $request,
        Branch $branch
    ): JsonResponse {
        $query = CrmLead::query()
            ->where('branch_id', $branch->id)
            ->with([
                'patientProfile',
                'assignedBusinessUser',
            ])
            ->latest();

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
            $query->where(
                'assigned_business_user_id',
                $request->integer('assigned_business_user_id')
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
        $validated = $request->validate([
            'patient_profile_id' => [
                'nullable',
                'integer',
                'exists:patient_profiles,id',
            ],
            'assigned_business_user_id' => [
                'nullable',
                'integer',
                'exists:business_user,id',
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

        $lead = CrmLead::create([
            'business_id' => $branch->business_id,
            'branch_id' => $branch->id,
            'patient_profile_id' => $validated['patient_profile_id'] ?? null,
            'assigned_business_user_id' =>
                $validated['assigned_business_user_id'] ?? null,
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'country_code' => $validated['country_code'] ?? null,
            'source' => $validated['source'] ?? 'manual',
            'status' => $validated['status'] ?? 'new',
            'priority' => $validated['priority'] ?? 'normal',
            'notes' => $validated['notes'] ?? null,
            'last_contacted_at' =>
                $validated['last_contacted_at'] ?? null,
            'next_follow_up_at' =>
                $validated['next_follow_up_at'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $lead->fresh([
                'patientProfile',
                'assignedBusinessUser',
                'branch',
            ]),
        ], 201);
    }

    public function show(
        Branch $branch,
        CrmLead $crmLead
    ): JsonResponse {
        if ($crmLead->branch_id !== $branch->id) {
            return response()->json([
                'success' => false,
                'message' => 'Bu lead belirtilen merkeze ait değil.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $crmLead->load([
                'patientProfile',
                'assignedBusinessUser',
                'branch',
            ]),
        ]);
    }

    public function update(
        Request $request,
        Branch $branch,
        CrmLead $crmLead
    ): JsonResponse {
        if ($crmLead->branch_id !== $branch->id) {
            return response()->json([
                'success' => false,
                'message' => 'Bu lead belirtilen merkeze ait değil.',
            ], 404);
        }

        $validated = $request->validate([
            'patient_profile_id' => [
                'nullable',
                'integer',
                'exists:patient_profiles,id',
            ],
            'assigned_business_user_id' => [
                'nullable',
                'integer',
                'exists:business_user,id',
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

        $crmLead->update($validated);

        return response()->json([
            'success' => true,
            'data' => $crmLead->fresh([
                'patientProfile',
                'assignedBusinessUser',
                'branch',
            ]),
        ]);
    }

    public function convert(
        Branch $branch,
        CrmLead $crmLead
    ): JsonResponse {
        if ($crmLead->branch_id !== $branch->id) {
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
            'data' => $crmLead->fresh([
                'patientProfile',
                'assignedBusinessUser',
                'branch',
            ]),
        ]);
    }

    public function markLost(
        Request $request,
        Branch $branch,
        CrmLead $crmLead
    ): JsonResponse {
        if ($crmLead->branch_id !== $branch->id) {
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
            'data' => $crmLead->fresh(),
        ]);
    }
}