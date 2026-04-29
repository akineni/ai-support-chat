<?php

namespace App\Repositories\Contracts;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface MessageRepositoryInterface
{
    public function create(array $data): Message;

    public function getConversationHistory(Conversation $conversation): LengthAwarePaginator;

    public function getHistoryForAi(Conversation $conversation): Collection;

    public function markAsRead(Conversation $conversation, string $senderType): void;
}