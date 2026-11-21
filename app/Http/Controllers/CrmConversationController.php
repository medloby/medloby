<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Conversation;
use App\Models\CrmLead;
use Illuminate\Http\JsonResponse;

class CrmConversationController extends Controller
{
    public function attachLead(
        Branch $branch,
        CrmLead $crmLead,
        Conversation $conversation
    ): JsonResponse {
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
}
