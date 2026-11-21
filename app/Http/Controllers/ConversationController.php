<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Conversation;
use App\Models\MessageAttachment;
use App\Models\PatientProfile;
use App\Models\TreatmentPackage;
use App\Services\PackageConversationNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ConversationController extends Controller
{
    public function __construct(private PackageConversationNotificationService $packageNotifications) {}

    public function index(Request $request): JsonResponse
    {
        $conversations = Conversation::query()
            ->visibleTo($request->user())
            ->with([
                'business',
                'branch',
                'patientProfile',
                'treatmentPackage:id,name',
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $conversations,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_id' => [
                'required_without:treatment_package_id',
                'nullable',
                'integer',
                'exists:businesses,id',
            ],
            'treatment_package_id' => ['nullable', 'integer'],
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

        if (isset($validated['treatment_package_id'])) {
            return $this->startPackageConversation($patientProfile, $validated);
        }

        $business = Business::findOrFail(
            $validated['business_id']
        );

        $branchId = $validated['branch_id'] ?? null;
        $branch = $branchId === null ? null : $business->branches()->find($branchId);

        if ($branchId !== null && $branch === null) {
            abort(
                422,
                'Seçilen şube bu işletmeye ait değil.'
            );
        }

        if (! $business->allowsCrmAccess() || ($branch !== null && $branch->status !== 'active')) {
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

    private function startPackageConversation(PatientProfile $patient, array $input): JsonResponse
    {
        $conversation = DB::transaction(function () use ($patient, $input) {
            PatientProfile::query()->lockForUpdate()->findOrFail($patient->id);

            $package = TreatmentPackage::query()
                ->availableForEnquiries()
                ->find($input['treatment_package_id']);

            if (! $package || (isset($input['business_id']) && (int) $input['business_id'] !== $package->business_id)
                || (isset($input['branch_id']) && (int) $input['branch_id'] !== $package->branch_id)) {
                throw ValidationException::withMessages([
                    'treatment_package_id' => 'Bu paket için şu anda görüşme başlatılamıyor.',
                ]);
            }

            $conversation = Conversation::firstOrCreate([
                'business_id' => $package->business_id,
                'branch_id' => $package->branch_id,
                'patient_profile_id' => $patient->id,
                'treatment_package_id' => $package->id,
                'status' => 'open',
            ], [
                'subject' => $package->name,
            ]);

            if ($conversation->wasRecentlyCreated) {
                $message = $conversation->messages()->create([
                    'sender_user_id' => $patient->user_id,
                    'body' => 'Bu tedavi paketi için teklif almak istiyorum.',
                    'message_type' => 'text',
                ]);
                $conversation->update(['last_message_at' => $message->created_at]);
                $this->packageNotifications->notifyRequest($conversation, $patient->user_id);
            }

            return $conversation;
        });

        return response()->json([
            'success' => true,
            'message' => 'Paket görüşmesi açıldı.',
            'data' => $conversation->load(['business', 'branch', 'patientProfile', 'treatmentPackage:id,name']),
        ], $conversation->wasRecentlyCreated ? 201 : 200);
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
                'conversation' => $conversation->load(['business', 'branch', 'patientProfile', 'treatmentPackage:id,name']),
                'messages' => $messages,
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function sendMessage(
        Request $request,
        Conversation $conversation
    ): JsonResponse {
        $this->ensureCanAccess(
            $request,
            $conversation
        );

        abort_unless(
            Conversation::query()->withActiveClinic()->whereKey($conversation->id)->exists(),
            422,
            'Bu sağlık merkezi şu anda iletişime açık değil.'
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

            $this->packageNotifications->notifyMessage($conversation, $request->user()->id);

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
        abort_unless(
            Conversation::query()->visibleTo($request->user())->whereKey($conversation->id)->exists(),
            403,
            'Bu görüşmeye erişim yetkiniz yok.'
        );
    }
}
