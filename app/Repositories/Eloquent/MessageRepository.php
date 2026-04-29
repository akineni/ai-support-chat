<?php

namespace App\Repositories\Eloquent;

use App\Enums\MessageSenderType;
use App\Models\Conversation;
use App\Models\Message;
use App\Repositories\Contracts\MessageRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class MessageRepository implements MessageRepositoryInterface
{
    public function create(array $data): Message
    {
        $message = Message::create($data);

        return $message->refresh()->load('attachments');
    }

    public function getConversationHistory(Conversation $conversation): LengthAwarePaginator
    {
        return $conversation->messages()
            ->with('attachments')
            ->oldest()
            ->paginate(50);
    }

    public function getHistoryForAi(Conversation $conversation): Collection
    {
        return $conversation->messages()
            ->whereIn('sender_type', [
                MessageSenderType::CUSTOMER->value,
                MessageSenderType::AI->value,
            ])
            ->with('attachments')
            ->latest()
            ->limit(20)
            ->get()
            ->reverse()
            ->values();
    }

    public function markAsRead(Conversation $conversation, string $senderType): void
    {
        $conversation->messages()
            ->where('sender_type', '!=', $senderType)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }
}
