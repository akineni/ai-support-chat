<?php

namespace App\Services\AiProviders;

use App\Enums\MessageSenderType;
use App\Models\Conversation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicProvider implements AiProviderInterface
{
    private string $apiKey;
    private string $model  = 'claude-haiku-4-5-20251001';
    private string $apiUrl = 'https://api.anthropic.com/v1/messages';

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.key');
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

        return $response->json('content.0.text', '');
    }

    // -------------------------------------------------------
    // Public — System Prompt
    // -------------------------------------------------------

    public static function systemPrompt(Conversation $conversation): string
    {
        return <<<PROMPT
        You are a friendly and professional customer support agent for [Company Name].
        Your name is "Support Team". You respond helpfully, concisely, and warmly.

        Rules:
        - Never reveal you are an AI unless directly and explicitly asked.
        - If asked directly whether you are an AI, answer honestly.
        - If you cannot resolve an issue, say "Let me escalate this to a specialist for you"
          and include the exact string [ESCALATE] at the very end of your response.
        - If the customer uses phrases like "speak to a human", "real agent", or "manager",
          respond with "Of course! Let me connect you with one of our team members right away."
          and include [ESCALATE] at the very end of your response.
        - If the customer seems very upset or frustrated, offer to escalate.
        - Keep responses concise and focused.
        - Do not make up policies, prices, or information you are not certain about.
        - If a customer attaches a non-image file (like a PDF or Word document), acknowledge
          it warmly and ask them to describe what help they need with it.

        Customer name: {$conversation->customer_name}
        PROMPT;
    }

    // -------------------------------------------------------
    // Private — Request Building
    // -------------------------------------------------------

    private function headers(): array
    {
        return [
            'x-api-key'         => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
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
            'system'     => self::systemPrompt($conversation),
            'messages'   => $this->buildMessages(
                $history,
                $newMessage,
                $imageUrls,
                $fileNames,
                $extractedContent
            ),
        ];
    }

    private function buildMessages(
        Collection $history,
        string $newMessage,
        array $imageUrls,
        array $fileNames,
        array $extractedContent
    ): array {
        $messages   = $this->buildHistoryMessages($history);
        $messages[] = $this->buildUserTurn($newMessage, $imageUrls, $fileNames, $extractedContent);

        return $messages;
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
         * Images are passed directly to the Anthropic vision API as URL sources.
         * Claude can natively see and reason about image content this way.
         * Only Cloudinary secure URLs for image/* mime types are passed here.
         */
        return array_map(fn($url) => [
            'type'   => 'image',
            'source' => ['type' => 'url', 'url' => $url],
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
        Log::error('Anthropic API error', [
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