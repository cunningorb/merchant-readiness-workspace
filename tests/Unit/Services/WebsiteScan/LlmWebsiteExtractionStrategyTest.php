<?php

namespace Tests\Unit\Services\WebsiteScan;

use App\Services\WebsiteScan\LlmWebsiteExtractionStrategy;
use App\Services\WebsiteScan\WebsiteScanResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LlmWebsiteExtractionStrategyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'llm.enabled' => true,
            'llm.provider' => 'groq',
            'llm.max_pages' => 6,
            'llm.max_input_characters' => 30000,
            'llm.prompt_version' => 'v1',
            'services.groq.key' => 'gsk_test_key',
            'services.groq.base_url' => 'https://api.groq.test/openai/v1',
            'services.groq.model' => 'test-model',
        ]);
    }

    private function scanWithReturnsPage(): WebsiteScanResult
    {
        return new WebsiteScanResult('https://merchant.test', [
            [
                'url' => 'https://merchant.test/pages/returns',
                'html' => '<p>Returns are accepted within 30 days. Final sale items cannot be returned. Exchanges are free for a different size.</p>',
            ],
        ]);
    }

    private function groqResponse(array $overrides = []): array
    {
        $fields = array_fill_keys(LlmWebsiteExtractionStrategy::FIELDS, [
            'value' => null,
            'confidence' => 'low',
            'evidence_quote' => null,
            'source_url' => null,
            'explanation' => null,
        ]);

        return array_replace($fields, $overrides);
    }

    public function test_returns_empty_when_disabled(): void
    {
        config(['llm.enabled' => false]);

        Http::fake(['api.groq.test/*' => Http::response([], 500)]);

        $result = app(LlmWebsiteExtractionStrategy::class)->extract($this->scanWithReturnsPage());

        $this->assertSame([], $result);
        Http::assertNothingSent();
    }

    public function test_verified_evidence_becomes_a_suggestion_with_provenance(): void
    {
        Http::fake([
            'api.groq.test/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode($this->groqResponse([
                        'return_window_days' => [
                            'value' => 30,
                            'confidence' => 'high',
                            'evidence_quote' => 'Returns are accepted within 30 days.',
                            'source_url' => 'https://merchant.test/pages/returns',
                            'explanation' => 'Explicit 30-day window stated.',
                        ],
                    ]))],
                ]],
            ], 200),
        ]);

        $suggestions = app(LlmWebsiteExtractionStrategy::class)->extract($this->scanWithReturnsPage());

        $this->assertCount(1, $suggestions);
        $this->assertSame('return_policy.window_days', $suggestions[0]['question_key']);
        $this->assertSame('15-30 days', $suggestions[0]['value']);
        $this->assertSame('high', $suggestions[0]['confidence']);
        $this->assertSame('groq', $suggestions[0]['provider']);
        $this->assertSame('test-model', $suggestions[0]['model']);
        $this->assertSame('v1', $suggestions[0]['prompt_version']);
        $this->assertSame('Returns are accepted within 30 days.', $suggestions[0]['evidence_snippet']);
    }

    public function test_rejects_evidence_quote_not_present_in_source_text(): void
    {
        Http::fake([
            'api.groq.test/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode($this->groqResponse([
                        'return_window_days' => [
                            'value' => 90,
                            'confidence' => 'high',
                            'evidence_quote' => 'Returns accepted for a full calendar year.',
                            'source_url' => 'https://merchant.test/pages/returns',
                            'explanation' => 'Fabricated.',
                        ],
                    ]))],
                ]],
            ], 200),
        ]);

        $suggestions = app(LlmWebsiteExtractionStrategy::class)->extract($this->scanWithReturnsPage());

        $this->assertSame([], $suggestions);
    }

    public function test_rejects_evidence_with_unknown_source_url(): void
    {
        Http::fake([
            'api.groq.test/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode($this->groqResponse([
                        'exchanges_offered' => [
                            'value' => true,
                            'confidence' => 'high',
                            'evidence_quote' => 'Exchanges are free for a different size.',
                            'source_url' => 'https://someone-elses-site.test/returns',
                            'explanation' => 'Wrong page.',
                        ],
                    ]))],
                ]],
            ], 200),
        ]);

        $suggestions = app(LlmWebsiteExtractionStrategy::class)->extract($this->scanWithReturnsPage());

        $this->assertSame([], $suggestions);
    }

    public function test_falls_back_to_empty_suggestions_and_logs_sanitized_warning_on_provider_failure(): void
    {
        Http::fake(['api.groq.test/*' => Http::response(['error' => 'boom'], 500)]);

        Log::spy();

        $suggestions = app(LlmWebsiteExtractionStrategy::class)->extract($this->scanWithReturnsPage());

        $this->assertSame([], $suggestions);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context = []) {
                $payload = $message.json_encode($context);

                return ! str_contains($payload, 'gsk_test_key')
                    && ! str_contains($payload, 'Returns are accepted within 30 days');
            });
    }

    public function test_does_not_throw_on_provider_timeout(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('timed out');
        });

        $suggestions = app(LlmWebsiteExtractionStrategy::class)->extract($this->scanWithReturnsPage());

        $this->assertSame([], $suggestions);
    }

    public function test_does_not_throw_on_invalid_json_response(): void
    {
        Http::fake([
            'api.groq.test/*' => Http::response(['choices' => [['message' => ['content' => '{not json']]]], 200),
        ]);

        $suggestions = app(LlmWebsiteExtractionStrategy::class)->extract($this->scanWithReturnsPage());

        $this->assertSame([], $suggestions);
    }

    public function test_caches_identical_requests_and_does_not_call_the_provider_twice(): void
    {
        Cache::flush();

        Http::fake([
            'api.groq.test/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode($this->groqResponse([
                        'exchanges_offered' => [
                            'value' => true,
                            'confidence' => 'high',
                            'evidence_quote' => 'Exchanges are free for a different size.',
                            'source_url' => 'https://merchant.test/pages/returns',
                            'explanation' => 'Stated directly.',
                        ],
                    ]))],
                ]],
            ], 200),
        ]);

        $strategy = app(LlmWebsiteExtractionStrategy::class);
        $scan = $this->scanWithReturnsPage();

        $first = $strategy->extract($scan);
        $second = $strategy->extract($scan);

        $this->assertSame($first, $second);
        Http::assertSentCount(1);
    }

    public function test_extract_fields_only_requests_the_given_subset(): void
    {
        Http::fake([
            'api.groq.test/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode($this->groqResponse())]]],
            ], 200),
        ]);

        app(LlmWebsiteExtractionStrategy::class)->extractFields($this->scanWithReturnsPage(), ['return_window_days']);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            $schema = $request['response_format']['json_schema']['schema'];

            return $schema['required'] === ['return_window_days'];
        });
    }
}
