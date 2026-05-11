<?php

namespace App\Services\AiProviders\Knowledge;

/**
 * ApiKnowledgeSource
 *
 * Sample knowledge source for loading knowledge from an external HTTP API.
 * Useful when knowledge is managed in a headless CMS, a shared microservice,
 * or any remote source that exposes a JSON API.
 *
 * Usage (in AppServiceProvider::boot()):
 *
 *   KnowledgeBase::setSource(
 *       new ApiKnowledgeSource(
 *           url: env('KNOWLEDGE_API_URL'),
 *           headers: ['Authorization' => 'Bearer ' . env('KNOWLEDGE_API_TOKEN')],
 *       )
 *   );
 */
class ApiKnowledgeSource implements KnowledgeSourceInterface
{
    public function __construct(
        private readonly string $url,
        private readonly array $headers = [],
    ) {}

    public function isAvailable(): bool
    {
        // TODO: check that a URL has been provided and is reachable
        // e.g. return !empty($this->url);
        throw new \RuntimeException('ApiKnowledgeSource::isAvailable() is not implemented.');
    }

    public function fetch(): string
    {
        // TODO: make an HTTP request to $this->url with $this->headers,
        // parse the response, and return a formatted string
        // for the AI to consume as its knowledge base.
        throw new \RuntimeException('ApiKnowledgeSource::fetch() is not implemented.');
    }
}