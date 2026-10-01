<?php

namespace App\Http\Controllers;

use App\Models\MessageAttachment;
use App\Models\PatientProfile;
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
                'Bu dosyaya erişim yetkiniz yok.'
            );
        }

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
                'Content-Type' => $attachment->mime_type,
                'Content-Disposition' => 'inline; filename="' .
                    addslashes($attachment->original_name) .
                    '"',
            ]
        );
    }
}