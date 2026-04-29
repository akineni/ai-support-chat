<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid'           => $this->uuid,
            'customer_name'  => $this->customer_name,
            'customer_email' => $this->customer_email,
            'status'         => $this->status?->value,
            'mode'           => $this->mode?->value,
            'assigned_agent' => $this->whenLoaded('assignedAgent', fn() => [
                'uuid'   => $this->assignedAgent->uuid,
                'name' => $this->assignedAgent->name,
            ]),
            'taken_over_at'  => $this->taken_over_at?->toISOString(),
            'created_at'     => $this->created_at->toISOString(),
        ];
    }
}
