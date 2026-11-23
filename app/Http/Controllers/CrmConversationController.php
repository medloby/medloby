<?php

namespace App\Http\Controllers;

use App\Http\Resources\ConversationResource;
use App\Models\Branch;
use App\Models\Conversation;
use App\Models\CrmLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CrmConversationController extends Controller
{
    public function index(Branch $branch, CrmLead $crmLead): JsonResponse
    {
        abort_unless($crmLead->business_id === $branch->business_id && $crmLead->branch_id === $branch->id, 404);

        $conversations = Conversation::query()
            ->where('business_id', $branch->business_id)
            ->where('branch_id', $branch->id)
            ->where(fn ($query) => $query->where('crm_lead_id', $crmLead->id)
                ->orWhere(fn ($available) => $available->whereNull('crm_lead_id')->whereHas('patientProfile')
                    ->when($crmLead->patient_profile_id !== null, fn ($patient) => $patient
                        ->where('patient_profile_id', $crmLead->patient_profile_id))))
            ->with('patientProfile:id,first_name,last_name')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get(['id', 'subject', 'patient_profile_id', 'crm_lead_id', 'last_message_at'])
            ->map(function (Conversation $conversation) use ($crmLead): array {
                $attached = $conversation->crm_lead_id !== null;
                $patient = $conversation->patientProfile;
                $matchesPatient = $patient !== null
                    && ($crmLead->patient_profile_id === null || $patient->id === $crmLead->patient_profile_id);

                return [
                    'id' => $conversation->id,
                    'subject' => $matchesPatient ? $conversation->subject : null,
                    'patient_name' => $matchesPatient ? trim($patient->first_name.' '.$patient->last_name) : null,
                    'attached' => $attached,
                    'can_open' => $attached && $matchesPatient,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $conversations,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function attachLead(
        Request $request,
        Branch $branch,
        CrmLead $crmLead,
        Conversation $conversation
    ): JsonResponse {
        return DB::transaction(function () use ($request, $branch, $crmLead, $conversation): JsonResponse {
            $crmLead = CrmLead::query()->lockForUpdate()->findOrFail($crmLead->id);
            $conversation = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);

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

            if ($conversation->patient_profile_id === null
                || ($crmLead->patient_profile_id === null && ! $request->boolean('link_patient'))
                || ($crmLead->patient_profile_id !== null && $conversation->patient_profile_id !== $crmLead->patient_profile_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Görüşme ile lead aynı hastaya ait değil.',
                ], 422);
            }

            if ($conversation->crm_lead_id !== null && $conversation->crm_lead_id !== $crmLead->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bu görüşme başka bir adaya bağlı.',
                ], 422);
            }

            if ($crmLead->patient_profile_id === null) {
                if ($crmLead->conversations()->where('business_id', $branch->business_id)
                    ->where('branch_id', $branch->id)->where('patient_profile_id', '!=', $conversation->patient_profile_id)->exists()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Adayın bağlı görüşmeleri aynı hastaya ait olmalıdır.',
                    ], 422);
                }

                $crmLead->update(['patient_profile_id' => $conversation->patient_profile_id]);
            }

            $conversation->update([
                'crm_lead_id' => $crmLead->id,
            ]);
            $summary = new ConversationResource($conversation->load(ConversationResource::DISPLAY_RELATIONS));

            return response()->json([
                'success' => true,
                'message' => 'Görüşme CRM leadine bağlandı.',
                'data' => [
                    ...$summary->resolve($request),
                    'crm_lead_id' => $crmLead->id,
                    'crm_lead' => $crmLead->only('id', 'business_id', 'branch_id'),
                ],
            ])->header('Cache-Control', 'private, no-store');
        }, attempts: 3);
    }

    public function detachLead(Branch $branch, CrmLead $crmLead, Conversation $conversation): JsonResponse
    {
        return DB::transaction(function () use ($branch, $crmLead, $conversation): JsonResponse {
            CrmLead::query()->forBranch($branch)->lockForUpdate()->findOrFail($crmLead->id, ['id']);
            $conversation = Conversation::query()
                ->where('business_id', $branch->business_id)->where('branch_id', $branch->id)
                ->lockForUpdate()->findOrFail($conversation->id);

            abort_if($conversation->crm_lead_id !== null && $conversation->crm_lead_id !== $crmLead->id,
                409, 'Görüşme bağlantısı değişti. Aday kaydını yenileyin.');

            $conversation->update(['crm_lead_id' => null]);

            return response()->json([
                'success' => true,
                'message' => 'Görüşmenin aday bağlantısı kaldırıldı.',
            ])->header('Cache-Control', 'private, no-store');
        }, attempts: 3);
    }
}
