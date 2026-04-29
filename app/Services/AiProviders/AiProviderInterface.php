<?php

namespace App\Services\AiProviders;

use App\Models\Conversation;
use Illuminate\Support\Collection;

interface AiProviderInterface
{
    public function generateReply(
        Conversation $conversation,
        Collection $history,
        string $newMessage,
        array $imageUrls = [],
        array $fileNames = [],
        array $extractedContent = []
    ): string;
}