<?php

return [

    /*
    |--------------------------------------------------------------------------
    | LLM-Assisted Website Extraction
    |--------------------------------------------------------------------------
    |
    | Feature-level configuration for the optional LLM strategy used by
    | App\Services\WebsiteScan\LlmWebsiteExtractionStrategy and the hybrid
    | strategy that combines it with the deterministic rules strategy. The
    | provider connection itself (base URL, API key, model, timeout) lives in
    | config/services.php alongside this app's other third-party credentials.
    |
    */

    'enabled' => env('LLM_ENABLED', false),

    'provider' => env('LLM_PROVIDER', 'groq'),

    'timeout_seconds' => (int) env('LLM_TIMEOUT_SECONDS', 15),

    'max_input_characters' => (int) env('LLM_MAX_INPUT_CHARACTERS', 30000),

    'max_pages' => (int) env('LLM_MAX_PAGES', 6),

    'daily_request_limit' => (int) env('LLM_DAILY_REQUEST_LIMIT', 100),

    /*
    |--------------------------------------------------------------------------
    | Additional Rate Limits
    |--------------------------------------------------------------------------
    |
    | Not exposed as top-level env vars in the feature spec; kept as internal
    | safety limits on top of the app-wide daily limit above. Deliberately
    | stricter than Groq's own account-level limits.
    |
    */

    'requests_per_assessment_per_hour' => (int) env('LLM_REQUESTS_PER_ASSESSMENT_PER_HOUR', 3),

    'requests_per_ip_per_hour' => (int) env('LLM_REQUESTS_PER_IP_PER_HOUR', 10),

    'prompt_version' => 'v1',
];
