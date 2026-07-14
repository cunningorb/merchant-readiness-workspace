<?php

namespace App\Services\WebsiteScan;

use App\Contracts\WebsiteExtractionStrategy;

class WebsiteExtractionStrategyResolver
{
    public function resolve(): WebsiteExtractionStrategy
    {
        return match (config('assessment.website_extraction.strategy', 'rules')) {
            'llm' => app(LlmWebsiteExtractionStrategy::class),
            'hybrid' => app(HybridWebsiteExtractionStrategy::class),
            default => app(RulesWebsiteExtractionStrategy::class),
        };
    }
}
