<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\MessageAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessageAttachmentController extends Controller
{
    public function view(
        Request $request,
        MessageAttachment $attachment
    ): StreamedResponse {
        $attachment->load([
            'message.conversation',
        ]);

        $conversation = $attachment->message->conversation;

        abort_unless(
            Conversation::query()->visibleTo($request->user())->whereKey($conversation->id)->exists(),
            403,
            'Bu dosyaya erişim yetkiniz yok.'
        );

        $disk = Storage::disk($attachment->disk);

        if (! $disk->exists($attachment->path)) {
            abort(
                404,
                'Dosya bulunamadı.'
            );
        }

        return $disk->response(
            $attachment->path,
            $attachment->original_name,
            [
                'Cache-Control' => 'private, no-store',
                'Content-Type' => $attachment->mime_type,
                'Content-Disposition' => 'inline; filename="'.
                    addslashes($attachment->original_name).
                    '"',
            ]
        );
    }
}
