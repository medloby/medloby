<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\MessageAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

        $inline = in_array($attachment->mime_type, ['image/jpeg', 'image/png', 'application/pdf', 'text/plain'], true);
        $filename = Str::afterLast(str_replace('\\', '/', $attachment->original_name), '/');
        $filename = trim(preg_replace('/[\x00-\x1f\x7f]/', '', $filename));
        if (in_array($filename, ['', '.', '..'], true)) {
            $filename = 'dosya';
        }

        return $disk->response($attachment->path, $filename, [
            'Cache-Control' => 'private, no-store',
            'Content-Type' => $inline ? $attachment->mime_type : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ], $inline ? 'inline' : 'attachment');
    }
}
