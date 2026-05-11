<?php

namespace App\Services\AiProviders;

use App\Models\Conversation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class OpenAiProvider extends BaseAiProvider
{
    private string $apiKey;
    private string $model  = 'gpt-4o';
    private string $apiUrl = 'https://api.openai.com/v1/chat/completions';

    public function __construct()
    {
        $this->apiKey = config('services.openai.key');
    }

    // -------------------------------------------------------
    // Public - Entry Point
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
            $this->logError($response, 'OpenAI');

            return $this->escalationFallback();
        }

        return $response->json('choices.0.message.content', '');
    }

    // -------------------------------------------------------
    // Protected - Provider Specific
    // -------------------------------------------------------

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
        ];
    }

    protected function buildPayload(
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
         *
         * Each message follows this shape:
         *   [
         *       'role'    => 'system' | 'user' | 'assistant',
         *       'content' => string | array of blocks,
         *   ]
         *
         * Content can be a plain string (history messages) or an array of blocks
         * when the message contains attachments (current user turn):
         *   [
         *       ['type' => 'image_url', 'image_url' => ['url' => '...']],  // image attachment
         *       ['type' => 'text',      'text'       => '...'],             // message text
         *   ]
         */
        return [
            'model'      => $this->model,
            'max_tokens' => 1024,
            'messages'   => array_merge(
                [$this->buildSystemMessage($conversation)],
                $this->buildHistoryMessages($history),
                [$this->buildUserTurn($newMessage, $imageUrls, $fileNames, $extractedContent)]
            ),
        ];
    }

    protected function buildImageBlocks(array $imageUrls): array
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

    // -------------------------------------------------------
    // Private - OpenAI Specific
    // -------------------------------------------------------

    private function buildSystemMessage(Conversation $conversation): array
    {
        return [
            'role'    => 'system',
            'content' => self::systemPrompt($conversation),
        ];
    }
}