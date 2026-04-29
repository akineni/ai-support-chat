<?php

namespace App\Repositories\Eloquent;

use App\Enums\ConversationMode;
use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ConversationRepository implements ConversationRepositoryInterface
{
    public function create(array $data): Conversation
    {
        $conversation = Conversation::create($data);

        return $conversation->refresh();
    }

    public function findBySessionToken(string $token): ?Conversation
    {
        return Conversation::where('session_token', $token)->first();
    }

    public function findByUuid(string $uuid): ?Conversation
    {
        return Conversation::with(['assignedAgent'])
            ->where('uuid', $uuid)
            ->first();
    }

    public function updateMode(
        Conversation $conversation,
        ConversationMode $mode,
        ?int $agentId = null
    ): Conversation {
        $conversation->update([
            'mode'              => $mode,
            'assigned_agent_id' => $agentId,
            'taken_over_at'     => $mode === ConversationMode::HUMAN ? now() : null,
        ]);

        return $conversation->fresh(['assignedAgent']);
    }

    public function updateStatus(Conversation $conversation, ConversationStatus $status): Conversation
    {
        $conversation->update(['status' => $status]);

        return $conversation->fresh();
    }

    public function allForAgent(?ConversationStatus $status = null, int $perPage = 20): LengthAwarePaginator
    {
        return Conversation::with([
                'assignedAgent',
                'messages' => fn($q) => $q->latest()->limit(1)->with('attachments'),
            ])
            ->when($status, fn($q) => $q->where('status', $status))
            ->latest()
            ->paginate($perPage);
    }
}