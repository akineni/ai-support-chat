<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'sender_type' => $this->sender_type?->value,
            'body'        => $this->body,
            'is_read'     => $this->is_read,
            'attachments' => AttachmentResource::collection(
                $this->whenLoaded('attachments')
            ),
            'created_at'  => $this->created_at->toISOString(),
        ];
    }
}
