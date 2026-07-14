<?php

namespace Tests\Unit\Services\WebsiteScan;

use App\Services\WebsiteScan\RulesWebsiteExtractionStrategy;
use App\Services\WebsiteScan\WebsiteScanResult;
use Tests\TestCase;

class RulesWebsiteExtractionStrategyTest extends TestCase
{
    public function test_extracts_company_platform_return_window_and_exchange_signal(): void
    {
        $html = <<<'HTML'
            <html>
                <head>
                    <title>Thistle & Bloom Apparel | Modern basics</title>
                    <script src="https://cdn.shopify.com/theme.js"></script>
                </head>
                <body>
                    <h1>Returns</h1>
                    <p>Returns are accepted within 30 days of delivery. Exchanges are available for a different size.</p>
                </body>
            </html>
        HTML;

        $suggestions = (new RulesWebsiteExtractionStrategy())->extract(new WebsiteScanResult(
            url: 'https://example.test',
            pages: [['url' => 'https://example.test/returns', 'html' => $html]],
        ));

        $byKey = collect($suggestions)->keyBy('question_key');

        $this->assertSame('Thistle & Bloom Apparel', $byKey['business.company_name']['value']);
        $this->assertSame('Shopify', $byKey['platform.ecommerce_platform']['value']);
        $this->assertSame('Custom automation', $byKey['platform.return_tools']['value']);
        $this->assertSame('15-30 days', $byKey['return_policy.window_days']['value']);
        $this->assertSame('Detailed policy page', $byKey['return_policy.policy_clarity']['value']);
        $this->assertTrue($byKey['exchanges.offered']['value']);
        $this->assertSame('high', $byKey['return_policy.window_days']['confidence']);
        $this->assertStringContainsString('30 days', $byKey['return_policy.window_days']['evidence_snippet']);
    }

    public function test_decodes_html_entities_from_meta_values_and_snippets(): void
    {
        $html = <<<'HTML'
            <html>
                <head>
                    <meta property="og:site_name" content="J&amp;D Productions, Inc.">
                    <script src="https://cdn.shopify.com/theme.js"></script>
                </head>
                <body>
                    <p>Returns are accepted within 45 days &amp; exchanges are available.</p>
                </body>
            </html>
        HTML;

        $suggestions = (new RulesWebsiteExtractionStrategy())->extract(new WebsiteScanResult(
            url: 'https://example.test',
            pages: [['url' => 'https://example.test', 'html' => $html]],
        ));

        $byKey = collect($suggestions)->keyBy('question_key');

        $this->assertSame('J&D Productions, Inc.', $byKey['business.company_name']['value']);
        $this->assertSame('J&D Productions, Inc.', $byKey['business.company_name']['evidence_snippet']);
        $this->assertStringContainsString('days & exchanges', $byKey['return_policy.window_days']['evidence_snippet']);
    }

    public function test_extracts_plain_text_contact_email(): void
    {
        $html = <<<'HTML'
            <html>
                <body>
                    <p>Contact us at Parts@FarmTractorRepair.com for help.</p>
                </body>
            </html>
        HTML;

        $suggestions = (new RulesWebsiteExtractionStrategy())->extract(new WebsiteScanResult(
            url: 'https://example.test/contact',
            pages: [['url' => 'https://example.test/contact', 'html' => $html]],
        ));

        $byKey = collect($suggestions)->keyBy('question_key');

        $this->assertSame('parts@farmtractorrepair.com', $byKey['business.contact_email']['value']);
        $this->assertSame('high', $byKey['business.contact_email']['confidence']);
        $this->assertStringContainsString('Parts@FarmTractorRepair.com', $byKey['business.contact_email']['evidence_snippet']);
    }

    public function test_derives_low_confidence_company_name_from_domain_when_page_has_no_title(): void
    {
        $suggestions = (new RulesWebsiteExtractionStrategy())->extract(new WebsiteScanResult(
            url: 'https://faithology.com',
            pages: [['url' => 'https://faithology.com/lander', 'html' => '<html><body><div id="root"></div></body></html>']],
        ));

        $byKey = collect($suggestions)->keyBy('question_key');

        $this->assertSame('Faithology', $byKey['business.company_name']['value']);
        $this->assertSame('low', $byKey['business.company_name']['confidence']);
        $this->assertSame('domain_name_fallback', $byKey['business.company_name']['metadata']['rule']);
    }

    public function test_strips_leading_phone_number_from_contact_email_match(): void
    {
        $html = <<<'HTML'
            <html>
                <body>
                    <p>J&D Productions, Inc Metamora, Michigan 810-664-0992sales@farmtractorrepair.com Shop now</p>
                </body>
            </html>
        HTML;

        $suggestions = (new RulesWebsiteExtractionStrategy())->extract(new WebsiteScanResult(
            url: 'https://example.test/contact',
            pages: [['url' => 'https://example.test/contact', 'html' => $html]],
        ));

        $byKey = collect($suggestions)->keyBy('question_key');

        $this->assertSame('sales@farmtractorrepair.com', $byKey['business.contact_email']['value']);
    }

    public function test_extracts_policy_clarity_from_return_language(): void
    {
        $html = <<<'HTML'
            <html>
                <body>
                    <h1>Returns FAQ</h1>
                    <p>Returns are accepted within 30 days.</p>
                </body>
            </html>
        HTML;

        $suggestions = (new RulesWebsiteExtractionStrategy())->extract(new WebsiteScanResult(
            url: 'https://example.test/pages/returns',
            pages: [['url' => 'https://example.test/pages/returns', 'html' => $html]],
        ));

        $byKey = collect($suggestions)->keyBy('question_key');

        $this->assertSame('Detailed policy page', $byKey['return_policy.policy_clarity']['value']);
        $this->assertSame('high', $byKey['return_policy.policy_clarity']['confidence']);
    }

    public function test_removes_scripts_before_extracting_policy_snippets(): void
    {
        $html = <<<'HTML'
            <html>
                <body>
                    <script>{"predictive":"15"}</script>
                    <p>Returns are accepted within 30 days.</p>
                </body>
            </html>
        HTML;

        $suggestions = (new RulesWebsiteExtractionStrategy())->extract(new WebsiteScanResult(
            url: 'https://example.test/pages/returns',
            pages: [['url' => 'https://example.test/pages/returns', 'html' => $html]],
        ));

        $byKey = collect($suggestions)->keyBy('question_key');

        $this->assertSame('15-30 days', $byKey['return_policy.window_days']['value']);
        $this->assertStringNotContainsString('predictive', $byKey['return_policy.window_days']['evidence_snippet']);
    }

    public function test_detects_returns_app_tooling_markers(): void
    {
        $html = <<<'HTML'
            <html><body><a href="https://loopreturns.com/start">Start a return</a></body></html>
        HTML;

        $suggestions = (new RulesWebsiteExtractionStrategy())->extract(new WebsiteScanResult(
            url: 'https://example.test/returns',
            pages: [['url' => 'https://example.test/returns', 'html' => $html]],
        ));

        $byKey = collect($suggestions)->keyBy('question_key');

        $this->assertSame('Returns app', $byKey['platform.return_tools']['value']);
        $this->assertSame('high', $byKey['platform.return_tools']['confidence']);
    }

    public function test_detects_manual_email_return_tooling(): void
    {
        $html = <<<'HTML'
            <html><body><p>Email us to start a return and our team will send instructions.</p></body></html>
        HTML;

        $suggestions = (new RulesWebsiteExtractionStrategy())->extract(new WebsiteScanResult(
            url: 'https://example.test/returns',
            pages: [['url' => 'https://example.test/returns', 'html' => $html]],
        ));

        $byKey = collect($suggestions)->keyBy('question_key');

        $this->assertSame('Email/spreadsheets', $byKey['platform.return_tools']['value']);
        $this->assertSame('medium', $byKey['platform.return_tools']['confidence']);
    }
}
