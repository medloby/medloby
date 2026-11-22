<?php

namespace App\Http\Resources;

use App\Models\MessageAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class ConversationMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...Arr::only($this->resource->attributesToArray(), [
                'id', 'conversation_id', 'sender_user_id', 'body', 'message_type',
                'read_at', 'edited_at', 'created_at', 'updated_at',
            ]),
            'sender' => $this->whenLoaded('sender', fn ($sender) => $sender->only('id', 'name')),
            'attachments' => $this->whenLoaded('attachments', fn ($attachments) => $attachments
                ->map(fn (MessageAttachment $attachment) => $attachment->only('id', 'original_name', 'mime_type', 'size'))),
        ];
    }
}
