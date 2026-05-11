<?php

namespace App\Services\AiProviders\Knowledge;

/**
 * NullKnowledgeSource
 *
 * The default no-op source used when no custom source is registered.
 * The AI will still work - just without any injected knowledge base.
 */
class NullKnowledgeSource implements KnowledgeSourceInterface
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function fetch(): string
    {
        return '';
    }
}