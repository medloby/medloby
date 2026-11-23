<?php

namespace App\Http\Controllers;

use App\Http\Resources\ConversationMessageResource;
use App\Http\Resources\ConversationResource;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\PatientProfile;
use App\Models\TreatmentPackage;
use App\Services\PackageConversationNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use League\Flysystem\FilesystemException;

class ConversationController extends Controller
{
    private const array MESSAGE_RELATIONS = [
        'sender:id,name', 'attachments:id,message_id,original_name,mime_type,size',
    ];

    public function __construct(private PackageConversationNotificationService $packageNotifications) {}

    public function index(Request $request): JsonResponse
    {
        $conversations = Conversation::query()
            ->visibleTo($request->user())
            ->with(ConversationResource::DISPLAY_RELATIONS)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->through(fn (Conversation $conversation) => new ConversationResource($conversation));

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
            'data' => new ConversationResource($conversation),
        ], 201)->header('Cache-Control', 'private, no-store');
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
            'data' => new ConversationResource($conversation->load(ConversationResource::DISPLAY_RELATIONS)),
        ], $conversation->wasRecentlyCreated ? 201 : 200)->header('Cache-Control', 'private, no-store');
    }

    public function show(
        Request $request,
        Conversation $conversation
    ): JsonResponse {
        $this->ensureCanAccess(
            $request,
            $conversation
        );

        $messageQuery = $conversation->messages()
            ->with(self::MESSAGE_RELATIONS)
            ->latest('id');
        $messages = $request->query->has('message_cursor')
            ? $messageQuery->cursorPaginate(50, cursorName: 'message_cursor', cursor: $this->messageHistoryCursor($request))
            : $messageQuery->paginate(50);

        return response()->json([
            'success' => true,
            'data' => [
                'conversation' => new ConversationResource($conversation->load(ConversationResource::DISPLAY_RELATIONS)),
                'messages' => $messages->through(fn (Message $message) => new ConversationMessageResource($message)),
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    private function messageHistoryCursor(Request $request): ?Cursor
    {
        $encoded = $request->validate(['message_cursor' => ['nullable', 'string', 'max:128']])['message_cursor'] ?? null;
        if ($encoded === null) {
            return null;
        }

        $cursor = Cursor::fromEncoded($encoded);
        $parameters = $cursor?->toArray();
        $messageId = $parameters['id'] ?? null;
        if (! is_int($messageId) || $messageId < 1 || ! is_bool($parameters['_pointsToNextItems'] ?? null)) {
            throw ValidationException::withMessages(['message_cursor' => 'Mesaj sayfası bağlantısı geçersiz.']);
        }

        return $cursor;
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

        $maxAttachmentSize = MessageAttachment::MAX_SIZE_MB;
        $validated = $request->validate([
            'body' => [
                'nullable',
                'string',
                'max:10000',
            ],

            'attachment' => [
                'nullable',
                'file',
                'max:'.($maxAttachmentSize * 1024),
                'mimes:jpg,jpeg,png,pdf',
            ],
        ], [
            'attachment.max' => "Ek dosya en fazla {$maxAttachmentSize} MB olabilir.",
            'attachment.uploaded' => 'Dosya yüklenemedi. Daha küçük bir dosyayla tekrar deneyin.',
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
                $disk = Storage::disk('private');
                $directory = 'conversations/'.$conversation->id;
                $filename = $file->hashName();
                $path = $directory.'/'.$filename;

                DB::afterRollBack(fn () => rescue(fn () => $disk->delete($path)));
                try {
                    $storedPath = $disk->putFileAs($directory, $file, $filename);
                } catch (FilesystemException $exception) {
                    report($exception);
                    $storedPath = false;
                }

                abort_if($storedPath === false, 503, 'Dosya kaydedilemedi. Lütfen tekrar deneyin.');

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
            'data' => new ConversationMessageResource($message->load(self::MESSAGE_RELATIONS)),
        ], 201)->header('Cache-Control', 'private, no-store');
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
