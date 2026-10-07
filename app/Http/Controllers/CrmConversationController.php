<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Conversation;
use App\Models\CrmLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmConversationController extends Controller
{
    public function attachLead(
        Request $request,
        Branch $branch,
        CrmLead $crmLead,
        Conversation $conversation
    ): JsonResponse {
        $businessUser = $this->resolveAuthorizedBusinessUser(
            $branch->business_id,
            $branch->id
        );

        if (! $businessUser) {
            return response()->json([
                'success' => false,
                'message' => 'Bu şubeye erişim yetkiniz yok.',
            ], 403);
        }

        if ($crmLead->business_id !== $branch->business_id) {
            return response()->json([
                'success' => false,
                'message' => 'Bu lead belirtilen işletmeye ait değil.',
            ], 404);
        }

        if ($crmLead->branch_id !== $branch->id) {
            return response()->json([
                'success' => false,
                'message' => 'Bu lead belirtilen şubeye ait değil.',
            ], 404);
        }

        if ($conversation->business_id !== $branch->business_id) {
            return response()->json([
                'success' => false,
                'message' => 'Bu görüşme belirtilen işletmeye ait değil.',
            ], 404);
        }

        if ($conversation->branch_id !== $branch->id) {
            return response()->json([
                'success' => false,
                'message' => 'Bu görüşme belirtilen şubeye ait değil.',
            ], 404);
        }

        if (
            $crmLead->patient_profile_id === null ||
            $conversation->patient_profile_id !== $crmLead->patient_profile_id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Görüşme ile lead aynı hastaya ait değil.',
            ], 422);
        }

        $conversation->update([
            'crm_lead_id' => $crmLead->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Görüşme CRM leadine bağlandı.',
            'data' => $conversation->fresh([
                'crmLead',
                'patientProfile',
                'business',
                'branch',
            ]),
        ]);
    }

    private function resolveAuthorizedBusinessUser(
        int $businessId,
        int $branchId
    ) {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        return \App\Models\BusinessUser::query()
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
}