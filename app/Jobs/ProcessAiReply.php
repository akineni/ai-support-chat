<?php

namespace App\Jobs;

use App\Enums\ConversationStatus;
use App\Enums\MessageSenderType;
use App\Events\AiTyping;
use App\Events\ConversationTakenOver;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Services\AiChatService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessAiReply implements ShouldQueue
{
    use Queueable;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(
        public readonly int    $conversationId,
        public readonly string $customerBody,
        public readonly array  $imageUrls,
        public readonly array  $fileNames,
        public readonly array  $extractedContent,
    ) {}

    // -------------------------------------------------------
    // Public — Job Entry Points
    // -------------------------------------------------------

    public function handle(
        AiChatService                   $aiChatService,
        MessageRepositoryInterface      $messageRepository,
        ConversationRepositoryInterface $conversationRepository,
    ): void {
        $conversation = $this->resolveConversation();

        if (!$conversation) {
            return;
        }

        if ($this->shouldSkip($conversation)) {
            return;
        }

        $this->broadcastTyping(true);

        $aiReplyRaw = $this->fetchAiReply($aiChatService, $messageRepository, $conversation);

        $this->persistAndBroadcastAiMessage($messageRepository, $aiReplyRaw);

        $this->broadcastTyping(false);

        if ($aiChatService->shouldEscalate($aiReplyRaw)) {
            $this->escalate($conversationRepository, $conversation);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->logFailure($exception);

        $this->broadcastTyping(false);

        $this->persistAndBroadcastFallbackMessage();

        $this->escalateOnFailure();
    }

    // -------------------------------------------------------
    // Private — Conversation Resolution
    // -------------------------------------------------------

    private function resolveConversation(): ?Conversation
    {
        $conversation = Conversation::find($this->conversationId);

        if (!$conversation) {
            Log::warning('ProcessAiReply: conversation not found', [
                'conversation_id' => $this->conversationId,
            ]);
        }

        return $conversation;
    }

    private function shouldSkip(Conversation $conversation): bool
    {
        // If agent took over while job was queued, skip AI reply
        return $conversation->isHumanMode();
    }

    // -------------------------------------------------------
    // Private — AI Reply
    // -------------------------------------------------------

    private function fetchAiReply(
        AiChatService              $aiChatService,
        MessageRepositoryInterface $messageRepository,
        Conversation               $conversation,
    ): string {
        $history = $messageRepository->getHistoryForAi($conversation);

        return $aiChatService->generateReply(
            $conversation,
            $history,
            $this->customerBody,
            $this->imageUrls,
            $this->fileNames,
            $this->extractedContent
        );
    }

    private function persistAndBroadcastAiMessage(
        MessageRepositoryInterface $messageRepository,
        string                     $aiReplyRaw,
    ): void {
        $cleanReply = app(AiChatService::class)->cleanResponse($aiReplyRaw);

        $aiMessage = $messageRepository->create([
            'conversation_id' => $this->conversationId,
            'sender_type'     => MessageSenderType::AI,
            'body'            => $cleanReply,
        ]);

        broadcast(new MessageSent($aiMessage->refresh()))->toOthers();
    }

    // -------------------------------------------------------
    // Private — Escalation
    // -------------------------------------------------------

    private function escalate(
        ConversationRepositoryInterface $conversationRepository,
        Conversation                    $conversation,
    ): void {
        $conversationRepository->updateStatus(
            $conversation,
            ConversationStatus::PENDING_HANDOVER
        );

        broadcast(new ConversationTakenOver($conversation->fresh()))->toOthers();
    }

    private function escalateOnFailure(): void
    {
        $conversation = Conversation::find($this->conversationId);

        if (!$conversation) {
            return;
        }

        app(ConversationRepositoryInterface::class)->updateStatus(
            $conversation,
            ConversationStatus::PENDING_HANDOVER
        );
    }

    // -------------------------------------------------------
    // Private — Fallback
    // -------------------------------------------------------

    private function persistAndBroadcastFallbackMessage(): void
    {
        $aiMessage = app(MessageRepositoryInterface::class)->create([
            'conversation_id' => $this->conversationId,
            'sender_type'     => MessageSenderType::AI,
            'body'            => 'I\'m sorry, I\'m having trouble responding right now. '
                               . 'Let me connect you with a human agent.',
        ]);

        broadcast(new MessageSent($aiMessage->refresh()))->toOthers();
    }

    // -------------------------------------------------------
    // Private — Broadcasting
    // -------------------------------------------------------

    private function broadcastTyping(bool $isTyping): void
    {
        broadcast(new AiTyping($this->conversationId, $isTyping));
    }

    // -------------------------------------------------------
    // Private — Logging
    // -------------------------------------------------------

    private function logFailure(\Throwable $exception): void
    {
        Log::error('ProcessAiReply job failed', [
            'conversation_id' => $this->conversationId,
            'error'           => $exception->getMessage(),
        ]);
    }
}