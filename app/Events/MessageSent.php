<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Message $message) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('conversation.' . $this->message->conversation->uuid),
            new Channel('agent.dashboard'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id'              => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'sender_type'     => $this->message->sender_type,
                'body'            => $this->message->body,
                'is_read'         => $this->message->is_read,
                'attachments'     => $this->message->attachments->map(fn($a) => [
                    'id'             => $a->id,
                    'file_url'       => $a->file_url,
                    'file_name'      => $a->file_name,
                    'file_type'      => $a->file_type,
                    'file_extension' => $a->file_extension,
                    'is_image'       => $a->is_image,
                ]),
                'created_at'      => $this->message->created_at->toISOString(),
            ],
        ];
    }
}