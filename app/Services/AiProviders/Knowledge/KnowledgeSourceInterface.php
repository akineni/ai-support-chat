<?php

namespace App\Services\AiProviders\Knowledge;

interface KnowledgeSourceInterface
{
    /**
     * Return the knowledge content to inject into the AI system prompt.
     *
     * The returned string can be anything - FAQ pairs, policy documents,
     * product descriptions, config values, Excel rows, API responses, etc.
     *
     * Return an empty string if no knowledge is available. The KnowledgeBase
     * runner will skip the injection entirely in that case.
     */
    public function fetch(): string;

    /**
     * Whether this source is available and ready to serve data.
     *
     * Called before fetch() - if this returns false, fetch() is never called.
     * Use this to check for table existence, config keys, file presence, etc.
     */
    public function isAvailable(): bool;
}