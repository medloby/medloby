<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Conversation;
use App\Models\PatientProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConversationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $patientProfile = PatientProfile::where('user_id', $user->id)->first();

        $businessIds = $user->businessMemberships()
            ->where('is_active', true)
            ->pluck('business_id');

        $conversations = Conversation::query()
            ->where(function ($query) use ($patientProfile, $businessIds) {
                if ($patientProfile) {
                    $query->where(
                        'patient_profile_id',
                        $patientProfile->id
                    );
                }

                if ($businessIds->isNotEmpty()) {
                    $query->orWhereIn(
                        'business_id',
                        $businessIds
                    );
                }
            })
            ->with([
                'business',
                'branch',
                'patientProfile',
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $conversations,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_id' => [
                'required',
                'integer',
                'exists:businesses,id',
            ],
            'branch_id' => [
                'nullable',
                'integer',
                'exists:branches,id',
            ],
            'subject' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $patientProfile = PatientProfile::where(
            'user_id',
            $request->user()->id
        )->first();

        if (! $patientProfile) {
            abort(403, 'Bu işlem yalnızca hasta hesabı ile yapılabilir.');
        }

        $business = Business::findOrFail(
            $validated['business_id']
        );

        if (
            $business->status !== 'active' ||
            ! $business->is_verified
        ) {
            abort(422, 'Bu sağlık merkezi şu anda iletişime açık değil.');
        }

        if (! empty($validated['branch_id'])) {
            $branchBelongsToBusiness = $business->branches()
                ->whereKey($validated['branch_id'])
                ->where('status', 'active')
                ->exists();

            if (! $branchBelongsToBusiness) {
                abort(
                    422,
                    'Seçilen merkez bu işletmeye ait değil veya aktif değil.'
                );
            }
        }

        $conversation = Conversation::create([
            'business_id' => $business->id,
            'branch_id' => $validated['branch_id'] ?? null,
            'patient_profile_id' => $patientProfile->id,
            'subject' => $validated['subject'] ?? null,
            'status' => 'open',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Görüşme başarıyla oluşturuldu.',
            'data' => $conversation->load([
                'business',
                'branch',
                'patientProfile',
            ]),
        ], 201);
    }

    public function show(
        Request $request,
        Conversation $conversation
    ): JsonResponse {
        $this->ensureCanAccess(
            $request,
            $conversation
        );

        $conversation->load([
            'business',
            'branch',
            'patientProfile',
        ]);

        $messages = $conversation->messages()
            ->with([
                'sender',
                'attachments',
            ])
            ->latest('id')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'data' => [
                'conversation' => $conversation,
                'messages' => $messages,
            ],
        ]);
    }

    public function sendMessage(
        Request $request,
        Conversation $conversation
    ): JsonResponse {
        $this->ensureCanAccess(
            $request,
            $conversation
        );

        if ($conversation->status !== 'open') {
            abort(422, 'Kapalı bir görüşmeye mesaj gönderilemez.');
        }

        $validated = $request->validate([
            'body' => [
                'required',
                'string',
                'max:10000',
            ],
        ]);

        $message = DB::transaction(function () use (
            $request,
            $conversation,
            $validated
        ) {
            $message = $conversation->messages()->create([
                'sender_user_id' => $request->user()->id,
                'body' => $validated['body'],
                'message_type' => 'text',
            ]);

            $conversation->update([
                'last_message_at' => $message->created_at,
            ]);

            return $message;
        });

        return response()->json([
            'success' => true,
            'message' => 'Mesaj başarıyla gönderildi.',
            'data' => $message->load([
                'sender',
                'attachments',
            ]),
        ], 201);
    }

    private function ensureCanAccess(
        Request $request,
        Conversation $conversation
    ): void {
        $user = $request->user();

        $isPatient = PatientProfile::where(
            'user_id',
            $user->id
        )
            ->whereKey($conversation->patient_profile_id)
            ->exists();

        $isBusinessUser = $user->businessMemberships()
            ->where('business_id', $conversation->business_id)
            ->where('is_active', true)
            ->exists();

        if (! $isPatient && ! $isBusinessUser) {
            abort(403, 'Bu görüşmeye erişim yetkiniz yok.');
        }
    }
}