<?php

namespace App\Services\AiProviders;

use App\Services\AiProviders\Knowledge\KnowledgeSourceInterface;
use App\Services\AiProviders\Knowledge\NullKnowledgeSource;
use Illuminate\Support\Facades\Cache;

/**
 * KnowledgeBase
 *
 * Runs whatever KnowledgeSourceInterface implementation is plugged in
 * and caches the result. The source is swappable at runtime - it can
 * pull from a database, config file, Excel sheet, external API, or anything else.
 *
 * -----------------------------------------------------------------------
 * REGISTERING A SOURCE (in AppServiceProvider::boot())
 * -----------------------------------------------------------------------
 *
 *   // Database source:
 *   KnowledgeBase::setSource(new DatabaseKnowledgeSource());
 *
 *   // Custom source (bring your own):
 *   KnowledgeBase::setSource(new ExcelKnowledgeSource(storage_path('knowledge/data.xlsx')));
 *   KnowledgeBase::setSource(new JsonFileKnowledgeSource(storage_path('knowledge/data.json')));
 *   KnowledgeBase::setSource(new ApiKnowledgeSource(env('KNOWLEDGE_API_URL')));
 *
 * -----------------------------------------------------------------------
 * FLUSHING THE CACHE (e.g. from an Observer or admin controller action)
 * -----------------------------------------------------------------------
 *
 *   KnowledgeBase::flush();
 *
 * -----------------------------------------------------------------------
 * NOT REGISTERING ANYTHING
 * -----------------------------------------------------------------------
 * If no source is registered (standalone / fresh install), the NullKnowledgeSource
 * is used automatically. The AI still works - just without injected knowledge.
 */
class KnowledgeBase
{
    private const CACHE_KEY = 'ai_knowledge_base';

    /**
     * Default TTL: 1 hour.
     * Override via config('services.ai.knowledge_cache_ttl') in seconds.
     */
    private const DEFAULT_CACHE_TTL = 3600;

    private static KnowledgeSourceInterface $source;

    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    // -------------------------------------------------------
    // Source Registration
    // -------------------------------------------------------

    /**
     * Plug in any knowledge source implementation.
     * Call this once in AppServiceProvider::boot().
     */
    public static function setSource(KnowledgeSourceInterface $source): void
    {
        static::$source = $source;

        // Flush stale cache whenever the source is swapped
        static::flush();
    }

    // -------------------------------------------------------
    // Public
    // -------------------------------------------------------

    /**
     * Returns the knowledge content string for injection into the system prompt.
     * Returns empty string if the source is unavailable or returns nothing.
     * Result is cached for the configured TTL.
     */
    public static function build(): string
    {
        $source = static::resolveSource();

        if (!$source->isAvailable()) {
            return '';
        }

        return Cache::remember(static::CACHE_KEY, static::ttl(), function () use ($source) {
            return $source->fetch();
        });
    }

    /**
     * Flush the cached knowledge base.
     * Call this after any data update (e.g. from an Eloquent observer or admin action).
     */
    public static function flush(): void
    {
        Cache::forget(static::CACHE_KEY);
    }

    // -------------------------------------------------------
    // Private
    // -------------------------------------------------------

    private static function resolveSource(): KnowledgeSourceInterface
    {
        return static::$source ?? new NullKnowledgeSource();
    }

    private static function ttl(): int
    {
        return (int) config('services.ai.knowledge_cache_ttl', static::DEFAULT_CACHE_TTL);
    }
}