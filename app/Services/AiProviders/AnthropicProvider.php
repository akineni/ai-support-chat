<?php

namespace App\Services\AiProviders;

use App\Models\Conversation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class AnthropicProvider extends BaseAiProvider
{
    private string $apiKey;
    private string $model  = 'claude-haiku-4-5-20251001';
    private string $apiUrl = 'https://api.anthropic.com/v1/messages';

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.key');
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
            $this->logError($response, 'Anthropic');

            return $this->escalationFallback();
        }

        return $response->json('content.0.text', '');
    }

    // -------------------------------------------------------
    // Protected - Provider Specific
    // -------------------------------------------------------

    protected function headers(): array
    {
        return [
            'x-api-key'         => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
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
         * Anthropic accepts a dedicated top-level 'system' parameter
         * separate from the messages array.
         */
        return [
            'model'      => $this->model,
            'max_tokens' => 1024,
            'system'     => self::systemPrompt($conversation),
            'messages'   => array_merge(
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
         * Images are passed directly to the Anthropic vision API as URL sources.
         * Claude can natively see and reason about image content this way.
         * Only Cloudinary secure URLs for image/* mime types are passed here.
         */
        return array_map(fn($url) => [
            'type'   => 'image',
            'source' => ['type' => 'url', 'url' => $url],
        ], $imageUrls);
    }
}