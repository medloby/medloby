<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Conversation;
use App\Models\MessageAttachment;
use App\Models\PatientProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ConversationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $patientProfile = PatientProfile::where(
            'user_id',
            $user->id
        )->first();

        $businessIds = $user->businessMemberships()
            ->where('is_active', true)
            ->pluck('business_id');

        $conversations = Conversation::query()
            ->where(function ($query) use (
                $patientProfile,
                $businessIds
            ) {
                $hasAccessCondition = false;

                if ($patientProfile) {
                    $query->where(
                        'patient_profile_id',
                        $patientProfile->id
                    );

                    $hasAccessCondition = true;
                }

                if ($businessIds->isNotEmpty()) {
                    if ($hasAccessCondition) {
                        $query->orWhereIn(
                            'business_id',
                            $businessIds
                        );
                    } else {
                        $query->whereIn(
                            'business_id',
                            $businessIds
                        );
                    }

                    $hasAccessCondition = true;
                }

                if (! $hasAccessCondition) {
                    $query->whereRaw('1 = 0');
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
            abort(
                403,
                'Bu işlem yalnızca hasta hesabı ile yapılabilir.'
            );
        }

        $business = Business::findOrFail(
            $validated['business_id']
        );

        $branchId = $validated['branch_id'] ?? null;

        if (
            $branchId !== null &&
            ! DB::table('branches')
                ->where('id', $branchId)
                ->where('business_id', $business->id)
                ->exists()
        ) {
            abort(
                422,
                'Seçilen şube bu işletmeye ait değil.'
            );
        }

        if (
            $business->status !== 'active' ||
            ! $business->is_verified
        ) {
            abort(
                422,
                'Bu sağlık merkezi şu anda iletişime açık değil.'
            );
        }

        $conversation = Conversation::create([
            'business_id' => $business->id,
            'branch_id' => $branchId,
            'patient_profile_id' => $patientProfile->id,
            'subject' => $validated['subject'] ?? null,
            'status' => 'open',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Görüşme başarıyla oluşturuldu.',
            'data' => $conversation,
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
            abort(
                422,
                'Kapalı görüşmeye mesaj gönderilemez.'
            );
        }

        $validated = $request->validate([
            'body' => [
                'nullable',
                'string',
                'max:10000',
            ],

            'attachment' => [
                'nullable',
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,pdf',
            ],
        ]);

        if (
            empty($validated['body']) &&
            ! $request->hasFile('attachment')
        ) {
            abort(
                422,
                'Mesaj veya dosya göndermelisiniz.'
            );
        }

        $message = DB::transaction(function () use (
            $request,
            $conversation,
            $validated
        ) {
            $message = $conversation->messages()->create([
                'sender_user_id' => $request->user()->id,
                'body' => $validated['body'] ?? null,
                'message_type' => $request->hasFile('attachment')
                    ? 'file'
                    : 'text',
            ]);

            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');

                $path = Storage::disk('private')->putFile(
                    'conversations/'.$conversation->id,
                    $file
                );

                MessageAttachment::create([
                    'message_id' => $message->id,
                    'disk' => 'private',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }

            $conversation->update([
                'last_message_at' => now(),
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
            abort(
                403,
                'Bu görüşmeye erişim yetkiniz yok.'
            );
        }
    }
}