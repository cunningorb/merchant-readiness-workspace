<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Recommendation Insight
    |--------------------------------------------------------------------------
    |
    | Feature-level configuration for App\Services\Ai\RecommendationInsightService.
    | This reuses the existing LlmClient/GroqLlmClient abstraction and the
    | shared services.groq.* provider connection unmodified — nothing here
    | duplicates that config, it only tunes this specific feature.
    |
    */

    'recommendation_insight' => [
        'enabled' => env('AI_RECOMMENDATION_INSIGHT_ENABLED', true),

        'cache_ttl_hours' => (int) env('AI_RECOMMENDATION_INSIGHT_CACHE_TTL_HOURS', 24),

        'prompt_version' => 'v1',

        // Report generation must not add more than ~5s on a cache miss. The
        // shared llm.timeout_seconds default (15s) is tuned for the website-
        // scan feature's larger payloads; this clamps the *effective* timeout
        // for this call only (via a transient config override — see
        // RecommendationInsightService), never the shared config value itself.
        'max_generation_seconds' => (int) env('AI_RECOMMENDATION_INSIGHT_MAX_SECONDS', 4),
    ],

];
