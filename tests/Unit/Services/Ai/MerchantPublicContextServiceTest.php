<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\MerchantPublicContextService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MerchantPublicContextServiceTest extends TestCase
{
    public function test_fetches_title_and_meta_description_for_a_private_looking_site(): void
    {
        Http::fake([
            'example.com' => Http::response(
                '<html><head><title>Northline Outdoor Supply</title>'
                .'<meta name="description" content="Gear for every trail."></head><body></body></html>'
            ),
        ]);

        $context = (new MerchantPublicContextService)->lookup('https://example.com');

        $this->assertSame('Northline Outdoor Supply', $context['title']);
        $this->assertSame('Gear for every trail.', $context['meta_description']);
        $this->assertFalse($context['is_public']);
        $this->assertNull($context['ticker']);
    }

    public function test_matches_a_trivially_known_public_company_by_title(): void
    {
        Http::fake([
            'example.org' => Http::response('<html><head><title>Nike. Just Do It.</title></head></html>'),
        ]);

        $context = (new MerchantPublicContextService)->lookup('https://example.org');

        $this->assertTrue($context['is_public']);
        $this->assertSame('NKE', $context['ticker']);
        $this->assertNotEmpty($context['sector']);
        $this->assertNotEmpty($context['industry']);
    }

    public function test_never_fabricates_a_market_cap(): void
    {
        Http::fake([
            'example.org' => Http::response('<html><head><title>Nike. Just Do It.</title></head></html>'),
        ]);

        $context = (new MerchantPublicContextService)->lookup('https://example.org');

        $this->assertArrayHasKey('market_cap', $context);
        $this->assertNull($context['market_cap']);
    }

    public function test_returns_null_on_a_failed_fetch(): void
    {
        Http::fake(['example.com' => Http::response('', 500)]);

        $context = (new MerchantPublicContextService)->lookup('https://example.com');

        $this->assertNull($context);
    }

    public function test_returns_null_on_a_connection_exception(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('timed out');
        });

        $context = (new MerchantPublicContextService)->lookup('https://example.com');

        $this->assertNull($context);
    }

    public function test_rejects_unsafe_urls_before_fetching(): void
    {
        Http::fake();

        $context = (new MerchantPublicContextService)->lookup('http://169.254.169.254/latest/meta-data');

        $this->assertNull($context);
        Http::assertNothingSent();
    }

    public function test_returns_null_when_no_title_or_description_present(): void
    {
        Http::fake(['example.com' => Http::response('<html><body>Hello</body></html>')]);

        $context = (new MerchantPublicContextService)->lookup('https://example.com');

        $this->assertNull($context['title']);
        $this->assertNull($context['meta_description']);
        $this->assertFalse($context['is_public']);
    }
}
