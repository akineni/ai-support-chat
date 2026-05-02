<?php

namespace App\Repositories\Contracts;

use App\Enums\ConversationMode;
use App\Enums\ConversationStatus;
use App\Models\Conversation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ConversationRepositoryInterface
{
    public function create(array $data): Conversation;

    public function findBySessionToken(string $token): ?Conversation;

    public function findByUuid(string $uuid): ?Conversation;

    public function updateMode(
        Conversation $conversation,
        ConversationMode $mode,
        ?int $agentId = null
    ): Conversation;

    public function updateStatus(Conversation $conversation, ConversationStatus $status): Conversation;

    public function allForAgent(?ConversationStatus $status = null, int $perPage = 20): LengthAwarePaginator;

    public function getUnreadCounts(): array;
}