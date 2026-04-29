<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'file_url'       => $this->file_url,
            'file_name'      => $this->file_name,
            'file_type'      => $this->file_type,
            'file_extension' => $this->file_extension,
            'file_size'      => $this->file_size,
            'is_image'       => $this->is_image,
            'created_at'     => $this->created_at->toISOString(),
        ];
    }
}
