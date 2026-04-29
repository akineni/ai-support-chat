<?php

namespace App\Services\AiProviders;

use App\Enums\MessageSenderType;
use App\Models\Conversation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiProvider implements AiProviderInterface
{
    private string $apiKey;
    private string $model  = 'gpt-4o';
    private string $apiUrl = 'https://api.openai.com/v1/chat/completions';

    public function __construct()
    {
        $this->apiKey = config('services.openai.key');
    }

    // -------------------------------------------------------
    // Public — Entry Point
    // -------------------------------------------------------

    public function generateReply(
        Conversation $conversation,
        Collection $history,
        string $newMessage,
        array $imageUrls = [],
        array $fileNames = [],
        array $extractedContent = []
    ): string {
        $response = Http::withHeaders($this->headers())
            ->post($this->apiUrl, $this->buildPayload(
                $conversation,
                $history,
                $newMessage,
                $imageUrls,
                $fileNames,
                $extractedContent
            ));

        if ($response->failed()) {
            $this->logError($response);

            return $this->escalationFallback();
        }

        return $response->json('choices.0.message.content', '');
    }

    // -------------------------------------------------------
    // Private — Request Building
    // -------------------------------------------------------

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
        ];
    }

    private function buildPayload(
        Conversation $conversation,
        Collection $history,
        string $newMessage,
        array $imageUrls,
        array $fileNames,
        array $extractedContent
    ): array {
        return [
            'model'      => $this->model,
            'max_tokens' => 1024,
            'messages'   => $this->buildMessages(
                $conversation,
                $history,
                $newMessage,
                $imageUrls,
                $fileNames,
                $extractedContent
            ),
        ];
    }

    private function buildMessages(
        Conversation $conversation,
        Collection $history,
        string $newMessage,
        array $imageUrls,
        array $fileNames,
        array $extractedContent
    ): array {
        /*
         * OpenAI does not have a dedicated system parameter like Anthropic.
         * The system prompt is passed as the first message with role "system".
         */
        $messages   = [$this->buildSystemMessage($conversation)];
        $messages   = array_merge($messages, $this->buildHistoryMessages($history));
        $messages[] = $this->buildUserTurn($newMessage, $imageUrls, $fileNames, $extractedContent);

        return $messages;
    }

    private function buildSystemMessage(Conversation $conversation): array
    {
        return [
            'role'    => 'system',
            'content' => AnthropicProvider::systemPrompt($conversation),
        ];
    }

    private function buildHistoryMessages(Collection $history): array
    {
        return $history->map(function ($msg) {
            return [
                'role'    => $msg->sender_type === MessageSenderType::CUSTOMER ? 'user' : 'assistant',
                'content' => $msg->body ?? '',
            ];
        })->values()->toArray();
    }

    private function buildUserTurn(
        string $newMessage,
        array $imageUrls,
        array $fileNames,
        array $extractedContent
    ): array {
        return [
            'role'    => 'user',
            'content' => $this->buildContentBlocks($newMessage, $imageUrls, $fileNames, $extractedContent),
        ];
    }

    private function buildContentBlocks(
        string $newMessage,
        array $imageUrls,
        array $fileNames,
        array $extractedContent
    ): array {
        $blocks = array_merge([], $this->buildImageBlocks($imageUrls));

        $blocks[] = $this->buildTextBlock($newMessage, $fileNames, $extractedContent);

        return $blocks;
    }

    private function buildImageBlocks(array $imageUrls): array
    {
        /*
         * IMAGE ATTACHMENTS
         * -----------------
         * Images are passed to the OpenAI vision API as image_url content blocks.
         * GPT-4o can natively see and reason about image content this way.
         * Only Cloudinary secure URLs for image/* mime types are passed here.
         */
        return array_map(fn($url) => [
            'type'      => 'image_url',
            'image_url' => ['url' => $url],
        ], $imageUrls);
    }

    private function buildTextBlock(
        string $newMessage,
        array $fileNames,
        array $extractedContent
    ): array {
        return [
            'type' => 'text',
            'text' => MessageContextBuilder::buildTextContent($newMessage, $fileNames, $extractedContent),
        ];
    }

    // -------------------------------------------------------
    // Private — Error Handling
    // -------------------------------------------------------

    private function logError($response): void
    {
        Log::error('OpenAI API error', [
            'status'   => $response->status(),
            'response' => $response->body(),
        ]);
    }

    private function escalationFallback(): string
    {
        return 'I\'m sorry, I\'m having trouble responding right now. '
             . 'Let me connect you with a human agent. [ESCALATE]';
    }
}