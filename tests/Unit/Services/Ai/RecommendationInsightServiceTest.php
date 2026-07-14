<?php

namespace Tests\Unit\Services\Ai;

use App\Models\Assessment;
use App\Models\Recommendation;
use App\Services\Ai\MerchantContextBuilder;
use App\Services\Ai\MerchantPublicContextService;
use App\Services\Ai\RecommendationInsightService;
use App\Services\Llm\Exceptions\LlmExtractionException;
use App\Services\Llm\LlmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class RecommendationInsightServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'llm.enabled' => true,
            'llm.provider' => 'groq',
            'llm.timeout_seconds' => 15,
            'ai.recommendation_insight.enabled' => true,
            'ai.recommendation_insight.cache_ttl_hours' => 24,
            'ai.recommendation_insight.prompt_version' => 'v1',
            'ai.recommendation_insight.max_generation_seconds' => 4,
            'services.groq.model' => 'test-model',
        ]);
    }

    private function validInsightPayload(array $overrides = []): array
    {
        return array_replace([
            'headline' => 'Exchanges are quietly costing you revenue',
            'executive_summary' => 'You process returns manually and rarely offer exchanges.',
            'merchant_context' => 'A single-warehouse apparel seller.',
            'business_reasoning' => 'Exchange-first flows keep the sale.',
            'priority_reason' => 'Highest-confidence opportunity identified.',
            'implementation_notes' => 'Default eligible returns into exchanges.',
            'confidence' => 'high',
            'disclaimer' => 'Estimate based on your answers — not a promise of results.',
        ], $overrides);
    }

    private function service(LlmClient $client): RecommendationInsightService
    {
        $publicContext = Mockery::mock(MerchantPublicContextService::class);
        $publicContext->shouldReceive('lookup')->andReturn(null);

        return new RecommendationInsightService($client, new MerchantContextBuilder($publicContext));
    }

    private function assessmentAndRecommendation(): array
    {
        $assessment = Assessment::factory()->create(['overall_score' => 68]);
        $recommendation = Recommendation::factory()->create([
            'assessment_id' => $assessment->id,
            'category' => 'exchanges',
        ]);

        return [$assessment, $recommendation];
    }

    public function test_returns_null_and_calls_no_provider_when_disabled(): void
    {
        config(['ai.recommendation_insight.enabled' => false]);
        [$assessment, $recommendation] = $this->assessmentAndRecommendation();

        $client = Mockery::mock(LlmClient::class);
        $client->shouldNotReceive('extractStructured');

        $insight = $this->service($client)->generate($assessment, $recommendation);

        $this->assertNull($insight);
    }

    public function test_returns_null_when_master_llm_switch_is_off(): void
    {
        config(['llm.enabled' => false]);
        [$assessment, $recommendation] = $this->assessmentAndRecommendation();

        $client = Mockery::mock(LlmClient::class);
        $client->shouldNotReceive('extractStructured');

        $insight = $this->service($client)->generate($assessment, $recommendation);

        $this->assertNull($insight);
    }

    public function test_prompt_never_contains_contact_email_or_contact_name(): void
    {
        $merchant = \App\Models\Merchant::factory()->create([
            'contact_name' => 'Jamie Rivera',
            'contact_email' => 'jamie@northline.test',
        ]);
        $assessment = Assessment::factory()->create(['merchant_id' => $merchant->id]);
        $recommendation = Recommendation::factory()->create([
            'assessment_id' => $assessment->id,
            'category' => 'exchanges',
        ]);

        $client = Mockery::mock(LlmClient::class);
        $client->shouldReceive('extractStructured')
            ->once()
            ->withArgs(function (array $messages) {
                $serialized = json_encode($messages);

                return ! str_contains($serialized, 'jamie@northline.test')
                    && ! str_contains($serialized, 'Jamie Rivera');
            })
            ->andReturn($this->validInsightPayload());

        $this->service($client)->generate($assessment, $recommendation);
    }

    public function test_generates_and_returns_an_insight_on_success(): void
    {
        [$assessment, $recommendation] = $this->assessmentAndRecommendation();

        $client = Mockery::mock(LlmClient::class);
        $client->shouldReceive('extractStructured')
            ->once()
            ->andReturn($this->validInsightPayload());

        $insight = $this->service($client)->generate($assessment, $recommendation);

        $this->assertNotNull($insight);
        $this->assertSame('Exchanges are quietly costing you revenue', $insight->headline);
        $this->assertSame('high', $insight->confidence);
    }

    public function test_prompt_sends_structured_merchant_context_not_raw_models(): void
    {
        [$assessment, $recommendation] = $this->assessmentAndRecommendation();

        $client = Mockery::mock(LlmClient::class);
        $client->shouldReceive('extractStructured')
            ->once()
            ->withArgs(function (array $messages, array $schema) {
                $userMessage = collect($messages)->firstWhere('role', 'user');
                $decoded = json_decode($userMessage['content'], true);

                $hasSystemPrompt = collect($messages)->contains(fn ($m) => $m['role'] === 'system');
                $schemaHasAllFields = $schema['required'] === [
                    'headline', 'executive_summary', 'merchant_context', 'business_reasoning',
                    'priority_reason', 'implementation_notes', 'confidence', 'disclaimer',
                ];

                return $hasSystemPrompt
                    && $schemaHasAllFields
                    && is_array($decoded['merchant_context'] ?? null)
                    && array_key_exists('readiness_score', $decoded['merchant_context']);
            })
            ->andReturn($this->validInsightPayload());

        $this->service($client)->generate($assessment, $recommendation);
    }

    public function test_caches_the_generated_insight(): void
    {
        Cache::flush();
        [$assessment, $recommendation] = $this->assessmentAndRecommendation();

        $client = Mockery::mock(LlmClient::class);
        $client->shouldReceive('extractStructured')
            ->once()
            ->andReturn($this->validInsightPayload());

        $service = $this->service($client);

        $first = $service->generate($assessment, $recommendation);
        $second = $service->generate($assessment, $recommendation);

        $this->assertEquals($first->toArray(), $second->toArray());
    }

    public function test_cache_key_is_scoped_by_assessment_recommendation_prompt_version_provider_and_model(): void
    {
        Cache::flush();
        [$assessmentA, $recommendationA] = $this->assessmentAndRecommendation();
        $assessmentB = Assessment::factory()->create();
        $recommendationB = Recommendation::factory()->create([
            'assessment_id' => $assessmentB->id,
            'category' => 'exchanges',
        ]);

        $client = Mockery::mock(LlmClient::class);
        $client->shouldReceive('extractStructured')
            ->twice()
            ->andReturn($this->validInsightPayload());

        $service = $this->service($client);
        $service->generate($assessmentA, $recommendationA);
        $service->generate($assessmentB, $recommendationB);
    }

    public function test_returns_null_and_does_not_throw_on_provider_failure(): void
    {
        [$assessment, $recommendation] = $this->assessmentAndRecommendation();

        $client = Mockery::mock(LlmClient::class);
        $client->shouldReceive('extractStructured')
            ->once()
            ->andThrow(LlmExtractionException::timedOut('groq'));

        Log::spy();

        $insight = $this->service($client)->generate($assessment, $recommendation);

        $this->assertNull($insight);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_returns_null_when_the_provider_returns_malformed_output(): void
    {
        [$assessment, $recommendation] = $this->assessmentAndRecommendation();

        $client = Mockery::mock(LlmClient::class);
        $client->shouldReceive('extractStructured')
            ->once()
            ->andReturn(['headline' => 'Missing everything else']);

        $insight = $this->service($client)->generate($assessment, $recommendation);

        $this->assertNull($insight);
    }

    public function test_malformed_output_is_not_cached(): void
    {
        Cache::flush();
        [$assessment, $recommendation] = $this->assessmentAndRecommendation();

        $client = Mockery::mock(LlmClient::class);
        $client->shouldReceive('extractStructured')
            ->twice()
            ->andReturn(['headline' => 'Missing everything else'], $this->validInsightPayload());

        $service = $this->service($client);

        $this->assertNull($service->generate($assessment, $recommendation));
        $this->assertNotNull($service->generate($assessment, $recommendation));
    }

    public function test_restores_the_shared_timeout_config_after_a_clamped_call(): void
    {
        [$assessment, $recommendation] = $this->assessmentAndRecommendation();

        $client = Mockery::mock(LlmClient::class);
        $client->shouldReceive('extractStructured')
            ->once()
            ->andReturnUsing(function () {
                $this->assertLessThanOrEqual(4, config('llm.timeout_seconds'));

                return $this->validInsightPayload();
            });

        $this->service($client)->generate($assessment, $recommendation);

        $this->assertSame(15, config('llm.timeout_seconds'));
    }
}
