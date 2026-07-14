<?php

namespace Tests\Unit\Services\WebsiteScan;

use App\Services\WebsiteScan\WebsiteScanResult;
use App\Services\WebsiteScan\WebsiteTextExtractor;
use Tests\TestCase;

class WebsiteTextExtractorTest extends TestCase
{
    public function test_strips_scripts_styles_and_tags_to_visible_text(): void
    {
        $html = '<html><head><style>.a{color:red}</style><script>alert(1)</script></head>'
            .'<body><p>Returns are accepted within 30 days.</p></body></html>';

        $scan = new WebsiteScanResult('https://merchant.test', [
            ['url' => 'https://merchant.test', 'html' => $html],
        ]);

        $blocks = (new WebsiteTextExtractor)->extractBlocks($scan, maxPages: 4, maxCharacters: 5000);

        $this->assertCount(1, $blocks);
        $this->assertSame('https://merchant.test', $blocks[0]['url']);
        $this->assertStringContainsString('Returns are accepted within 30 days.', $blocks[0]['text']);
        $this->assertStringNotContainsString('alert(1)', $blocks[0]['text']);
        $this->assertStringNotContainsString('color:red', $blocks[0]['text']);
    }

    public function test_strips_nav_header_footer_and_cookie_banner_boilerplate(): void
    {
        $html = '<body>'
            .'<nav>Home About Contact</nav>'
            .'<header>Free shipping over $50</header>'
            .'<div id="cookie-consent-banner">We use cookies to improve your experience.</div>'
            .'<main><p>Exchanges are free for a different size.</p></main>'
            .'<footer>&copy; 2026 Merchant Inc.</footer>'
            .'</body>';

        $scan = new WebsiteScanResult('https://merchant.test', [
            ['url' => 'https://merchant.test', 'html' => $html],
        ]);

        $blocks = (new WebsiteTextExtractor)->extractBlocks($scan, maxPages: 4, maxCharacters: 5000);

        $this->assertStringContainsString('Exchanges are free for a different size.', $blocks[0]['text']);
        $this->assertStringNotContainsString('Home About Contact', $blocks[0]['text']);
        $this->assertStringNotContainsString('cookies to improve', $blocks[0]['text']);
        $this->assertStringNotContainsString('Merchant Inc.', $blocks[0]['text']);
    }

    public function test_retains_source_url_per_block(): void
    {
        $scan = new WebsiteScanResult('https://merchant.test', [
            ['url' => 'https://merchant.test', 'html' => '<p>Home page.</p>'],
            ['url' => 'https://merchant.test/pages/returns', 'html' => '<p>30-day returns.</p>'],
        ]);

        $blocks = (new WebsiteTextExtractor)->extractBlocks($scan, maxPages: 4, maxCharacters: 5000);

        $this->assertSame(
            ['https://merchant.test', 'https://merchant.test/pages/returns'],
            array_column($blocks, 'url'),
        );
    }

    public function test_limits_by_max_pages(): void
    {
        $pages = [];
        for ($i = 0; $i < 4; $i++) {
            $pages[] = ['url' => "https://merchant.test/page-{$i}", 'html' => "<p>Page {$i} content.</p>"];
        }

        $scan = new WebsiteScanResult('https://merchant.test', $pages);

        $blocks = (new WebsiteTextExtractor)->extractBlocks($scan, maxPages: 2, maxCharacters: 5000);

        $this->assertCount(2, $blocks);
    }

    public function test_limits_total_characters_across_blocks(): void
    {
        $scan = new WebsiteScanResult('https://merchant.test', [
            ['url' => 'https://merchant.test/a', 'html' => '<p>'.str_repeat('a', 100).'</p>'],
            ['url' => 'https://merchant.test/b', 'html' => '<p>'.str_repeat('b', 100).'</p>'],
        ]);

        $blocks = (new WebsiteTextExtractor)->extractBlocks($scan, maxPages: 4, maxCharacters: 120);

        $totalCharacters = array_sum(array_map(fn (array $block) => mb_strlen($block['text']), $blocks));

        $this->assertLessThanOrEqual(120, $totalCharacters);
    }

    public function test_skips_pages_with_no_visible_text(): void
    {
        $scan = new WebsiteScanResult('https://merchant.test', [
            ['url' => 'https://merchant.test/empty', 'html' => '<script>track()</script><style>.a{}</style>'],
            ['url' => 'https://merchant.test/real', 'html' => '<p>Final sale items cannot be returned.</p>'],
        ]);

        $blocks = (new WebsiteTextExtractor)->extractBlocks($scan, maxPages: 4, maxCharacters: 5000);

        $this->assertCount(1, $blocks);
        $this->assertSame('https://merchant.test/real', $blocks[0]['url']);
    }
}
