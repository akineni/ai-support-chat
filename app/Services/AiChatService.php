<?php

namespace App\Services;

use App\Models\Conversation;
use App\Services\AiProviders\AiProviderInterface;
use App\Services\AiProviders\AnthropicProvider;
use App\Services\AiProviders\OpenAiProvider;
use Illuminate\Support\Collection;

class AiChatService
{
    private AiProviderInterface $provider;

    public function __construct()
    {
        $this->provider = $this->resolveProvider();
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
        return $this->provider->generateReply(
            $conversation,
            $history,
            $newMessage,
            $imageUrls,
            $fileNames,
            $extractedContent
        );
    }

    public function shouldEscalate(string $aiResponse): bool
    {
        return str_contains($aiResponse, '[ESCALATE]');
    }

    public function cleanResponse(string $aiResponse): string
    {
        return trim(str_replace('[ESCALATE]', '', $aiResponse));
    }

    // -------------------------------------------------------
    // Private — Provider Resolution
    // -------------------------------------------------------

    private function resolveProvider(): AiProviderInterface
    {
        // Use app() instead of new so providers can be mocked via the service container in tests
        return match (config('services.ai.provider')) {
            'openai'    => app(OpenAiProvider::class),
            'anthropic' => app(AnthropicProvider::class),
            default     => app(AnthropicProvider::class),
        };
    }
}
