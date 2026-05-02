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
        $perPage = (int) config('chat.messages_per_page', 50);

        return $conversation->messages()
            ->with('attachments')
            ->latest()        // order by created_at DESC to get last {$perPage} messages
            ->paginate($perPage)
            ->tap(function ($paginator) {
                // Reverse the collection so messages display oldest → newest
                $paginator->setCollection(
                    $paginator->getCollection()->reverse()->values()
                );
            });
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
