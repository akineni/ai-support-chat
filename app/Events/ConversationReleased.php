<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationReleased implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Conversation $conversation) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('agent.dashboard'),
            new Channel('conversation.' . $this->conversation->uuid),
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversation.released';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_uuid' => $this->conversation->uuid,
            'mode'              => $this->conversation->mode,
            'status'            => $this->conversation->status,
        ];
    }
}