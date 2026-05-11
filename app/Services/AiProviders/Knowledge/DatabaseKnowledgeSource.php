<?php

namespace App\Services\AiProviders\Knowledge;

/**
 * DatabaseKnowledgeSource
 *
 * Sample knowledge source for loading knowledge from a database.
 * Implement isAvailable() and fetch() according to your database schema.
 *
 * Usage (in AppServiceProvider::boot()):
 *
 *   KnowledgeBase::setSource(new DatabaseKnowledgeSource());
 */
class DatabaseKnowledgeSource implements KnowledgeSourceInterface
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
        // TODO: check that the required tables exist and the models are available
        // e.g. return Schema::hasTable('your_table') && class_exists(YourModel::class);
        throw new \RuntimeException('DatabaseKnowledgeSource::isAvailable() is not implemented.');
    }

    public function fetch(): string
    {
        // TODO: query your database and return a formatted string
        // for the AI to consume as its knowledge base.
        throw new \RuntimeException('DatabaseKnowledgeSource::fetch() is not implemented.');
    }
}