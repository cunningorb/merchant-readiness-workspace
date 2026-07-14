<?php

namespace Tests\Unit\Services\WebsiteScan;

use App\Services\WebsiteScan\HybridWebsiteExtractionStrategy;
use App\Services\WebsiteScan\LlmWebsiteExtractionStrategy;
use App\Services\WebsiteScan\RulesWebsiteExtractionStrategy;
use App\Services\WebsiteScan\WebsiteExtractionStrategyResolver;
use Tests\TestCase;

class WebsiteExtractionStrategyResolverTest extends TestCase
{
    public function test_defaults_to_rules_strategy(): void
    {
        config(['assessment.website_extraction.strategy' => 'rules']);

        $this->assertInstanceOf(
            RulesWebsiteExtractionStrategy::class,
            app(WebsiteExtractionStrategyResolver::class)->resolve()
        );
    }

    public function test_can_resolve_llm_strategy_without_calling_a_provider(): void
    {
        config(['assessment.website_extraction.strategy' => 'llm']);

        $this->assertInstanceOf(
            LlmWebsiteExtractionStrategy::class,
            app(WebsiteExtractionStrategyResolver::class)->resolve()
        );
    }

    public function test_can_resolve_hybrid_strategy_without_calling_a_provider(): void
    {
        config(['assessment.website_extraction.strategy' => 'hybrid']);

        $this->assertInstanceOf(
            HybridWebsiteExtractionStrategy::class,
            app(WebsiteExtractionStrategyResolver::class)->resolve()
        );
    }
}
