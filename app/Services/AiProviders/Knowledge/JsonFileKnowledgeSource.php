<?php

namespace App\Services\AiProviders\Knowledge;

/**
 * JsonFileKnowledgeSource
 *
 * Sample knowledge source for loading knowledge from a local JSON file.
 * Useful when knowledge is exported from a CMS, Notion, or any tool
 * that can produce JSON, without needing a database.
 *
 * Usage (in AppServiceProvider::boot()):
 *
 *   KnowledgeBase::setSource(
 *       new JsonFileKnowledgeSource(storage_path('knowledge/data.json'))
 *   );
 */
class JsonFileKnowledgeSource implements KnowledgeSourceInterface
{
    public function __construct(
        private readonly string $filePath
    ) {}

    public function isAvailable(): bool
    {
        // TODO: check the file exists and is readable
        // e.g. return file_exists($this->filePath) && is_readable($this->filePath);
        throw new \RuntimeException('JsonFileKnowledgeSource::isAvailable() is not implemented.');
    }

    public function fetch(): string
    {
        // TODO: read and decode the JSON file, then return a formatted string
        // for the AI to consume as its knowledge base.
        throw new \RuntimeException('JsonFileKnowledgeSource::fetch() is not implemented.');
    }
}