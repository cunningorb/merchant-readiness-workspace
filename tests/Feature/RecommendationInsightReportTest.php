<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Merchant;
use App\Models\Recommendation;
use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecommendationInsightReportTest extends TestCase
{
    use RefreshDatabase;

    private function reportedAssessmentWithRecommendation(): Report
    {
        // website: null avoids MerchantPublicContextService attempting a real
        // DNS lookup against Faker's random (non-resolving) fake domain name.
        $merchant = Merchant::factory()->create(['website' => null]);
        $assessment = Assessment::factory()->create([
            'merchant_id' => $merchant->id,
            'overall_score' => 68,
            'overall_tier' => 'Developing',
            'section_scores' => ['exchanges' => ['score' => 55, 'tier' => 'Developing']],
        ]);

        Recommendation::factory()->create([
            'assessment_id' => $assessment->id,
            'category' => 'exchanges',
            'priority' => 'high',
        ]);

        return Report::factory()->for($assessment)->create(['published_at' => now()]);
    }

    private function fakeGroqInsight(): void
    {
        Http::fake([
            'api.groq.test/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'headline' => 'Exchanges are quietly costing you revenue',
                        'executive_summary' => 'You rarely offer exchanges today.',
                        'merchant_context' => 'A returns-heavy apparel seller.',
                        'business_reasoning' => 'Exchange-first flows keep the sale.',
                        'priority_reason' => 'Highest-confidence opportunity.',
                        'implementation_notes' => 'Default eligible returns into exchanges.',
                        'confidence' => 'medium',
                        'disclaimer' => 'Not a promise of results.',
                    ])],
                ]],
            ], 200),
        ]);
    }

    public function test_report_includes_ai_insight_when_llm_is_enabled_and_available(): void
    {
        config([
            'llm.enabled' => true,
            'ai.recommendation_insight.enabled' => true,
            'services.groq.key' => 'gsk_test',
            'services.groq.base_url' => 'https://api.groq.test/openai/v1',
            'services.groq.model' => 'test-model',
        ]);
        $this->fakeGroqInsight();

        $report = $this->reportedAssessmentWithRecommendation();

        $response = $this->getJson("/api/reports/{$report->token}");

        $response->assertOk()
            ->assertJsonPath('aiInsight.headline', 'Exchanges are quietly costing you revenue')
            ->assertJsonPath('aiInsight.confidence', 'medium');
    }

    public function test_report_has_no_ai_insight_when_the_feature_is_disabled(): void
    {
        config(['llm.enabled' => false]);
        Http::fake(['api.groq.test/*' => Http::response(['error' => 'should not be called'], 500)]);

        $report = $this->reportedAssessmentWithRecommendation();

        $response = $this->getJson("/api/reports/{$report->token}");

        $response->assertOk()->assertJsonPath('aiInsight', null);
        Http::assertNothingSent();
    }

    public function test_report_still_succeeds_when_the_provider_is_unavailable(): void
    {
        config([
            'llm.enabled' => true,
            'ai.recommendation_insight.enabled' => true,
            'services.groq.key' => 'gsk_test',
            'services.groq.base_url' => 'https://api.groq.test/openai/v1',
            'services.groq.model' => 'test-model',
        ]);
        Http::fake(['api.groq.test/*' => Http::response(['error' => 'down'], 503)]);

        $report = $this->reportedAssessmentWithRecommendation();

        $response = $this->getJson("/api/reports/{$report->token}");

        $response->assertOk()->assertJsonPath('aiInsight', null);
    }

    public function test_contact_endpoint_never_calls_the_llm_provider(): void
    {
        config([
            'llm.enabled' => true,
            'ai.recommendation_insight.enabled' => true,
            'services.groq.key' => 'gsk_test',
            'services.groq.base_url' => 'https://api.groq.test/openai/v1',
            'services.groq.model' => 'test-model',
        ]);
        Http::fake(['api.groq.test/*' => Http::response(['error' => 'should not be called'], 500)]);

        $report = $this->reportedAssessmentWithRecommendation();

        $this->postJson("/api/reports/{$report->token}/contact")->assertOk();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.groq.test'));
    }

    public function test_api_key_never_appears_in_the_report_json_response(): void
    {
        config([
            'llm.enabled' => true,
            'ai.recommendation_insight.enabled' => true,
            'services.groq.key' => 'gsk_super_secret',
            'services.groq.base_url' => 'https://api.groq.test/openai/v1',
            'services.groq.model' => 'test-model',
        ]);
        $this->fakeGroqInsight();

        $report = $this->reportedAssessmentWithRecommendation();

        $response = $this->getJson("/api/reports/{$report->token}");

        $this->assertStringNotContainsString('gsk_super_secret', $response->getContent());
    }
}
