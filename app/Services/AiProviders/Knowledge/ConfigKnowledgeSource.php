<?php

namespace App\Services\AiProviders\Knowledge;

/**
 * ConfigKnowledgeSource
 *
 * Sample knowledge source for loading knowledge from a Laravel config file.
 * Good for small, static knowledge bases that rarely change.
 *
 * Create a config file, e.g. config/knowledge.php, and return your data from it.
 *
 * Usage (in AppServiceProvider::boot()):
 *
 *   KnowledgeBase::setSource(new ConfigKnowledgeSource('knowledge'));
 */
class ConfigKnowledgeSource implements KnowledgeSourceInterface
{
    public function __construct(
        private readonly string $configKey = 'knowledge'
    ) {}

    public function isAvailable(): bool
    {
        // TODO: check the config key exists and holds usable data
        // e.g. return is_array(config($this->configKey)) && !empty(config($this->configKey));
        throw new \RuntimeException('ConfigKnowledgeSource::isAvailable() is not implemented.');
    }

    public function fetch(): string
    {
        // TODO: read config($this->configKey) and return a formatted string
        // for the AI to consume as its knowledge base.
        throw new \RuntimeException('ConfigKnowledgeSource::fetch() is not implemented.');
    }
}