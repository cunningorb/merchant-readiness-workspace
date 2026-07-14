<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Services\WebsiteScan\LlmWebsiteExtractionStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebsiteScanHybridTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'llm.enabled' => true,
            'llm.max_pages' => 6,
            'llm.max_input_characters' => 30000,
            'llm.prompt_version' => 'v1',
            'llm.requests_per_assessment_per_hour' => 5,
            'llm.requests_per_ip_per_hour' => 5,
            'llm.daily_request_limit' => 5,
            'services.groq.key' => 'gsk_super_secret_key',
            'services.groq.base_url' => 'https://api.groq.test/openai/v1',
            'services.groq.model' => 'test-model',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function groqFields(array $overrides = []): array
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

    private function fakeSiteAndGroq(array $groqOverrides = []): void
    {
        Http::fake([
            'example.com*' => Http::response(
                '<html><body><p>Final sale items cannot be returned. No restocking fee applies.</p></body></html>'
            ),
            'api.groq.test/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode($this->groqFields($groqOverrides))]]],
            ], 200),
        ]);
    }

    public function test_rules_strategy_never_calls_the_llm_provider(): void
    {
        config(['assessment.website_extraction.strategy' => 'rules']);
        $this->fakeSiteAndGroq();
        $assessment = Assessment::factory()->create();

        $this->postJson("/api/assessments/{$assessment->id}/website-scan", ['url' => 'example.com'])
            ->assertCreated();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.groq.test'));
    }

    public function test_llm_strategy_calls_the_provider(): void
    {
        config(['assessment.website_extraction.strategy' => 'llm']);
        $this->fakeSiteAndGroq();
        $assessment = Assessment::factory()->create();

        $this->postJson("/api/assessments/{$assessment->id}/website-scan", ['url' => 'example.com'])
            ->assertCreated();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.groq.test'));
    }

    public function test_hybrid_strategy_creates_ai_assisted_evidence_with_provenance(): void
    {
        config(['assessment.website_extraction.strategy' => 'hybrid']);
        $this->fakeSiteAndGroq([
            'restocking_fee' => [
                'value' => 'No restocking fee.',
                'confidence' => 'high',
                'evidence_quote' => 'No restocking fee applies.',
                'source_url' => 'https://example.com',
                'explanation' => 'Stated directly.',
            ],
        ]);
        $assessment = Assessment::factory()->create();

        $response = $this->postJson("/api/assessments/{$assessment->id}/website-scan", ['url' => 'example.com'])
            ->assertCreated();

        $insight = collect($response->json('evidence')['website_insight.restocking_fee'] ?? [])->first();

        $this->assertNotNull($insight);
        $this->assertSame('groq', $insight['provider']);
        $this->assertSame('Website scan — AI-assisted', $insight['source_label']);
        $this->assertFalse($insight['requires_confirmation']);

        $this->assertDatabaseHas('assessment_answer_evidence', [
            'assessment_id' => $assessment->id,
            'question_key' => 'website_insight.restocking_fee',
            'provider' => 'groq',
            'model' => 'test-model',
            'prompt_version' => 'v1',
            'source_label' => 'Website scan — AI-assisted',
        ]);
    }

    public function test_website_insight_fields_never_become_assessment_answers(): void
    {
        config(['assessment.website_extraction.strategy' => 'hybrid']);
        $this->fakeSiteAndGroq([
            'restocking_fee' => [
                'value' => 'No restocking fee.',
                'confidence' => 'high',
                'evidence_quote' => 'No restocking fee applies.',
                'source_url' => 'https://example.com',
                'explanation' => 'Stated directly.',
            ],
        ]);
        $assessment = Assessment::factory()->create();

        $this->postJson("/api/assessments/{$assessment->id}/website-scan", ['url' => 'example.com'])
            ->assertCreated();

        $this->assertDatabaseMissing('assessment_answers', [
            'assessment_id' => $assessment->id,
            'question_key' => 'website_insight.restocking_fee',
        ]);
    }

    public function test_scan_still_completes_when_the_llm_provider_is_unavailable(): void
    {
        config(['assessment.website_extraction.strategy' => 'hybrid']);
        Http::fake([
            'example.com*' => Http::response(
                '<html><body><p>Returns are accepted within 30 days.</p></body></html>'
            ),
            'api.groq.test/*' => Http::response(['error' => 'service unavailable'], 503),
        ]);
        $assessment = Assessment::factory()->create();

        $response = $this->postJson("/api/assessments/{$assessment->id}/website-scan", ['url' => 'example.com']);

        $response->assertCreated()->assertJsonPath('website_scan.status', 'completed');
        $this->assertSame(
            '15-30 days',
            collect($response->json('evidence')['return_policy.window_days'] ?? [])->first()['value'],
        );
    }

    public function test_equal_confidence_conflict_is_shown_but_never_auto_answered(): void
    {
        config(['assessment.website_extraction.strategy' => 'hybrid']);
        Http::fake([
            'example.com*' => Http::response(
                '<html><body><p>Return eligibility varies by product. See our help center.</p></body></html>'
            ),
            'api.groq.test/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode($this->groqFields([
                    'policy_clarity' => [
                        'value' => 'unclear',
                        'confidence' => 'medium',
                        'evidence_quote' => 'Return eligibility varies by product.',
                        'source_url' => 'https://example.com',
                        'explanation' => 'Ambiguous phrasing.',
                    ],
                ]))]]],
            ], 200),
        ]);
        $assessment = Assessment::factory()->create();

        $response = $this->postJson("/api/assessments/{$assessment->id}/website-scan", ['url' => 'example.com'])
            ->assertCreated();

        $candidates = $response->json('evidence')['return_policy.policy_clarity'] ?? [];

        $this->assertCount(2, $candidates);
        $this->assertTrue($candidates[0]['requires_confirmation']);
        $this->assertTrue($candidates[1]['requires_confirmation']);

        $this->assertDatabaseMissing('assessment_answers', [
            'assessment_id' => $assessment->id,
            'question_key' => 'return_policy.policy_clarity',
        ]);
    }

    public function test_api_key_never_appears_in_the_json_response(): void
    {
        config(['assessment.website_extraction.strategy' => 'hybrid']);
        $this->fakeSiteAndGroq([
            'restocking_fee' => [
                'value' => 'No restocking fee.',
                'confidence' => 'high',
                'evidence_quote' => 'No restocking fee applies.',
                'source_url' => 'https://example.com',
                'explanation' => 'Stated directly.',
            ],
        ]);
        $assessment = Assessment::factory()->create();

        $response = $this->postJson("/api/assessments/{$assessment->id}/website-scan", ['url' => 'example.com'])
            ->assertCreated();

        $this->assertStringNotContainsString('gsk_super_secret_key', $response->getContent());
    }

    public function test_daily_llm_limit_falls_back_to_rules_without_failing_the_scan(): void
    {
        config([
            'assessment.website_extraction.strategy' => 'hybrid',
            'llm.daily_request_limit' => 1,
            'llm.requests_per_assessment_per_hour' => 100,
            'llm.requests_per_ip_per_hour' => 100,
        ]);
        $this->fakeSiteAndGroq();

        $first = Assessment::factory()->create();
        $second = Assessment::factory()->create();

        $this->postJson("/api/assessments/{$first->id}/website-scan", ['url' => 'example.com'])->assertCreated();

        $groqCallsAfterFirst = collect(Http::recorded())
            ->filter(fn ($pair) => str_contains($pair[0]->url(), 'api.groq.test'))
            ->count();

        $response = $this->postJson("/api/assessments/{$second->id}/website-scan", ['url' => 'example.com']);

        $response->assertCreated();

        $groqCallsAfterSecond = collect(Http::recorded())
            ->filter(fn ($pair) => str_contains($pair[0]->url(), 'api.groq.test'))
            ->count();

        $this->assertSame(1, $groqCallsAfterFirst);
        $this->assertSame($groqCallsAfterFirst, $groqCallsAfterSecond);
    }
}
