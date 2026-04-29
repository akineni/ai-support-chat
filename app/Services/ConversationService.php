<?php

namespace App\Services;

use App\Enums\ConversationMode;
use App\Enums\ConversationStatus;
use App\Enums\MessageSenderType;
use App\Events\ConversationReleased;
use App\Events\ConversationTakenOver;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Models\Conversation;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class ConversationService
{
    public function __construct(
        protected ConversationRepositoryInterface $conversationRepository,
        protected MessageRepositoryInterface      $messageRepository,
    ) {}

    // -------------------------------------------------------
    // Public — Conversation Lifecycle
    // -------------------------------------------------------

    public function startConversation(array $data): array
    {
        $sessionToken = (string) Str::uuid();

        $conversation = $this->conversationRepository->create([
            'customer_name'  => $data['customer_name'],
            'customer_email' => $data['customer_email'] ?? null,
            'session_token'  => $sessionToken,
        ]);

        return [
            'conversation'  => $conversation,
            'session_token' => $sessionToken,
        ];
    }

    public function agentTakeover(string $uuid, int $agentId): Conversation
    {
        $conversation = $this->getConversationByUuid($uuid);

        $this->assertNotInHumanMode($conversation);

        $conversation = $this->switchToHumanMode($conversation, $agentId);

        $this->broadcastTakeover($conversation);

        return $conversation;
    }

    public function releaseToAi(string $uuid): Conversation
    {
        $conversation = $this->getConversationByUuid($uuid);

        $this->assertNotInAiMode($conversation);

        $conversation = $this->switchToAiMode($conversation);

        $this->broadcastRelease($conversation);

        return $conversation;
    }

    // -------------------------------------------------------
    // Public — Lookups
    // -------------------------------------------------------

    public function getConversationBySessionToken(string $sessionToken): Conversation
    {
        $conversation = $this->conversationRepository->findBySessionToken($sessionToken);

        if (!$conversation) {
            throw new NotFoundException('Conversation');
        }

        return $conversation;
    }

    public function getConversationByUuid(string $uuid): Conversation
    {
        $conversation = $this->conversationRepository->findByUuid($uuid);

        if (!$conversation) {
            throw new NotFoundException('Conversation');
        }

        return $conversation;
    }

    // -------------------------------------------------------
    // Public — Message History
    // -------------------------------------------------------

    public function getConversationHistoryForCustomer(string $sessionToken): LengthAwarePaginator
    {
        $conversation = $this->getConversationBySessionToken($sessionToken);

        $this->messageRepository->markAsRead($conversation, MessageSenderType::CUSTOMER->value);

        return $this->messageRepository->getConversationHistory($conversation);
    }

    public function getConversationHistoryForAgent(string $uuid): LengthAwarePaginator
    {
        $conversation = $this->getConversationByUuid($uuid);

        $this->messageRepository->markAsRead($conversation, MessageSenderType::AGENT->value);

        return $this->messageRepository->getConversationHistory($conversation);
    }

    // -------------------------------------------------------
    // Public — Agent Dashboard
    // -------------------------------------------------------

    public function getAllConversationsForAgent(
        ?string $status = null,
        int $perPage = 20
    ): LengthAwarePaginator {
        $resolvedStatus = $this->resolveStatus($status);

        return $this->conversationRepository->allForAgent($resolvedStatus, $perPage);
    }

    // -------------------------------------------------------
    // Private — Mode Switching
    // -------------------------------------------------------

    private function switchToHumanMode(Conversation $conversation, int $agentId): Conversation
    {
        $conversation = $this->conversationRepository->updateMode(
            $conversation,
            ConversationMode::HUMAN,
            $agentId
        );

        return $this->conversationRepository->updateStatus(
            $conversation,
            ConversationStatus::OPEN
        );
    }

    private function switchToAiMode(Conversation $conversation): Conversation
    {
        return $this->conversationRepository->updateMode(
            $conversation,
            ConversationMode::AI,
            null
        );
    }

    // -------------------------------------------------------
    // Private — Guards
    // -------------------------------------------------------

    private function assertNotInHumanMode(Conversation $conversation): void
    {
        if ($conversation->isHumanMode()) {
            throw new ConflictException('Conversation is already assigned to an agent.');
        }
    }

    private function assertNotInAiMode(Conversation $conversation): void
    {
        if ($conversation->isAiMode()) {
            throw new ConflictException('Conversation is already in AI mode.');
        }
    }

    // -------------------------------------------------------
    // Private — Broadcasting
    // -------------------------------------------------------

    private function broadcastTakeover(Conversation $conversation): void
    {
        broadcast(new ConversationTakenOver($conversation))->toOthers();
    }

    private function broadcastRelease(Conversation $conversation): void
    {
        broadcast(new ConversationReleased($conversation))->toOthers();
    }

    // -------------------------------------------------------
    // Private — Helpers
    // -------------------------------------------------------

    private function resolveStatus(?string $status): ?ConversationStatus
    {
        if ($status === null) {
            return null;
        }

        $resolved = ConversationStatus::tryFrom($status);

        if (is_null($resolved)) {
            throw new \InvalidArgumentException('Invalid status value provided.');
        }

        return $resolved;
    }
}