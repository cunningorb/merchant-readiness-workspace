<?php

namespace Tests\Unit\Services\Ai;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Models\AssessmentAnswerEvidence;
use App\Models\AssessmentBenchmarkComparison;
use App\Models\AssessmentOpportunity;
use App\Models\Merchant;
use App\Models\MerchantOrderMetric;
use App\Models\MerchantReturnMetric;
use App\Models\Recommendation;
use App\Models\WebsiteScan;
use App\Services\Ai\MerchantContextBuilder;
use App\Services\Ai\MerchantPublicContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MerchantContextBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function builder(): MerchantContextBuilder
    {
        $publicContext = Mockery::mock(MerchantPublicContextService::class);
        $publicContext->shouldReceive('lookup')->andReturn(null);

        return new MerchantContextBuilder($publicContext);
    }

    private function answer(Assessment $assessment, string $key, mixed $value, string $section): void
    {
        AssessmentAnswer::factory()->create([
            'assessment_id' => $assessment->id,
            'question_key' => $key,
            'section' => $section,
            'value' => $value,
        ]);
    }

    public function test_builds_full_context_from_a_richly_populated_assessment(): void
    {
        $merchant = Merchant::factory()->create([
            'company_name' => 'Northline Outdoor Supply',
            'contact_name' => 'Jamie Rivera',
            'contact_email' => 'jamie@northline.test',
            'website' => 'https://northline.test',
        ]);
        $assessment = Assessment::factory()->create([
            'merchant_id' => $merchant->id,
            'overall_score' => 68,
            'overall_tier' => 'Developing',
            'section_scores' => [
                'return_policy' => ['score' => 62, 'tier' => 'Developing'],
                'manual_operations' => ['score' => 55, 'tier' => 'Developing'],
                'exchanges' => ['score' => 71, 'tier' => 'Established'],
                'platform' => ['score' => 74, 'tier' => 'Established'],
            ],
        ]);

        $this->answer($assessment, 'business.company_name', 'Northline Outdoor Supply', 'business');
        $this->answer($assessment, 'business.contact_email', 'jamie@northline.test', 'business');
        $this->answer($assessment, 'platform.ecommerce_platform', 'Shopify', 'platform');
        $this->answer($assessment, 'manual_operations.weekly_hours', '21-50', 'manual_operations');
        $this->answer($assessment, 'catalog.sku_count', '5,001-25,000', 'catalog');
        $this->answer($assessment, 'catalog.fit_sensitive_categories', ['Apparel', 'Footwear'], 'catalog');

        $recommendation = Recommendation::factory()->create([
            'assessment_id' => $assessment->id,
            'title' => 'Switch to exchange-first automation',
            'category' => 'exchanges',
            'priority' => 'high',
        ]);

        $opportunity = AssessmentOpportunity::factory()->create([
            'assessment_id' => $assessment->id,
            'type' => AssessmentOpportunity::TYPE_RETAINED_REVENUE,
            'minimum_value' => 18000,
            'maximum_value' => 26000,
            'confidence' => 'medium',
            'sort_order' => 0,
        ]);

        config(['assessment.opportunities.recommendation_category_to_opportunity_type' => [
            'exchanges' => AssessmentOpportunity::TYPE_RETAINED_REVENUE,
        ]]);

        MerchantOrderMetric::factory()->create([
            'merchant_id' => $merchant->id,
            'assessment_id' => $assessment->id,
            'annualized_order_volume' => 42000,
            'average_order_value' => 85.50,
        ]);

        MerchantReturnMetric::factory()->create([
            'merchant_id' => $merchant->id,
            'assessment_id' => $assessment->id,
            'estimated_refund_rate' => 0.17,
            'exchange_share' => 0.22,
        ]);

        AssessmentBenchmarkComparison::factory()->create([
            'assessment_id' => $assessment->id,
            'label' => 'Return rate vs. outdoor-apparel peers',
            'interpretation' => 'Slightly above the peer range.',
            'sort_order' => 0,
        ]);

        WebsiteScan::factory()->create([
            'assessment_id' => $assessment->id,
            'pages_scanned' => 3,
            'extracted_data' => [
                ['question_key' => 'platform.ecommerce_platform', 'value' => 'Shopify'],
                ['question_key' => 'exchanges.offered', 'value' => true],
            ],
        ]);

        AssessmentAnswerEvidence::factory()->create([
            'assessment_id' => $assessment->id,
            'question_key' => 'exchanges.offered',
            'source_label' => 'Website scan',
            'confidence' => 'medium',
            'evidence_snippet' => 'Exchanges are offered on returns within 30 days.',
        ]);

        $context = $this->builder()->build($assessment->fresh(), $recommendation);
        $array = $context->toArray();

        $this->assertSame('Northline Outdoor Supply', $array['company_name']);
        $this->assertSame('Apparel, Footwear', $array['industry']);
        $this->assertSame('https://northline.test', $array['website']);
        $this->assertSame('Shopify', $array['platform']);
        $this->assertSame(68, $array['readiness_score']);
        $this->assertSame('21-50', $array['manual_hours']);
        $this->assertEqualsWithDelta(0.17, $array['estimated_return_rate'], 0.0001);
        $this->assertEqualsWithDelta(0.22, $array['estimated_exchange_rate'], 0.0001);
        $this->assertEqualsWithDelta(42000.0, $array['estimated_order_volume'], 0.01);
        $this->assertEqualsWithDelta(85.50, $array['estimated_aov'], 0.01);
        $this->assertStringContainsString('5,001-25,000', $array['catalog_complexity']);
        $this->assertSame(62, $array['policy_score']);
        $this->assertSame(55, $array['automation_score']);
        $this->assertSame(71, $array['exchange_score']);
        $this->assertSame(74, $array['risk_score']);
        $this->assertCount(1, $array['peer_benchmark_summary']);
        $this->assertSame('Return rate vs. outdoor-apparel peers', $array['peer_benchmark_summary'][0]['label']);
        $this->assertNotNull($array['website_scan_summary']);
        $this->assertSame(3, $array['website_scan_summary']['pages_scanned']);
        $this->assertNotEmpty($array['evidence_summaries']);
        $this->assertSame('Switch to exchange-first automation', $array['top_recommendation']['title']);
        $this->assertNotNull($array['opportunity_estimate']);
        $this->assertSame(AssessmentOpportunity::TYPE_RETAINED_REVENUE, $array['opportunity_estimate']['type']);
        $this->assertSame('medium', $array['confidence']);
    }

    public function test_optional_fields_are_null_or_empty_when_no_data_is_available(): void
    {
        $merchant = Merchant::factory()->create(['website' => null]);
        $assessment = Assessment::factory()->create(['merchant_id' => $merchant->id, 'section_scores' => []]);
        $recommendation = Recommendation::factory()->create([
            'assessment_id' => $assessment->id,
            'category' => 'return_policy',
        ]);

        $context = $this->builder()->build($assessment->fresh(), $recommendation);
        $array = $context->toArray();

        $this->assertNull($array['industry']);
        $this->assertNull($array['website']);
        $this->assertNull($array['manual_hours']);
        $this->assertNull($array['estimated_return_rate']);
        $this->assertNull($array['estimated_exchange_rate']);
        $this->assertNull($array['estimated_order_volume']);
        $this->assertNull($array['estimated_aov']);
        $this->assertNull($array['catalog_complexity']);
        $this->assertNull($array['policy_score']);
        $this->assertSame([], $array['peer_benchmark_summary']);
        $this->assertNull($array['website_scan_summary']);
        $this->assertSame([], $array['evidence_summaries']);
        $this->assertNull($array['opportunity_estimate']);
        $this->assertSame('low', $array['confidence']);
    }

    public function test_public_context_is_null_when_the_public_context_service_returns_null(): void
    {
        $merchant = Merchant::factory()->create(['website' => 'https://example.com']);
        $assessment = Assessment::factory()->create(['merchant_id' => $merchant->id]);
        $recommendation = Recommendation::factory()->create(['assessment_id' => $assessment->id]);

        $publicContext = Mockery::mock(MerchantPublicContextService::class);
        $publicContext->shouldReceive('lookup')->once()->with('https://example.com')->andReturn(null);

        $context = (new MerchantContextBuilder($publicContext))->build($assessment->fresh(), $recommendation);

        $this->assertNull($context->toArray()['public_context']);
    }

    public function test_public_context_lookup_is_skipped_entirely_when_the_merchant_has_no_website(): void
    {
        $merchant = Merchant::factory()->create(['website' => null]);
        $assessment = Assessment::factory()->create(['merchant_id' => $merchant->id]);
        $recommendation = Recommendation::factory()->create(['assessment_id' => $assessment->id]);

        $publicContext = Mockery::mock(MerchantPublicContextService::class);
        $publicContext->shouldNotReceive('lookup');

        $context = (new MerchantContextBuilder($publicContext))->build($assessment->fresh(), $recommendation);

        $this->assertNull($context->toArray()['public_context']);
    }

    public function test_never_includes_contact_email_or_contact_name(): void
    {
        $merchant = Merchant::factory()->create([
            'contact_name' => 'Jamie Rivera',
            'contact_email' => 'jamie@northline.test',
        ]);
        $assessment = Assessment::factory()->create(['merchant_id' => $merchant->id]);
        $recommendation = Recommendation::factory()->create(['assessment_id' => $assessment->id]);

        $context = $this->builder()->build($assessment->fresh(), $recommendation);
        $serialized = json_encode($context->toArray());

        $this->assertStringNotContainsString('jamie@northline.test', $serialized);
        $this->assertStringNotContainsString('Jamie Rivera', $serialized);
    }

    public function test_evidence_summaries_are_scoped_to_the_recommendation_category(): void
    {
        $assessment = Assessment::factory()->create();
        $recommendation = Recommendation::factory()->create([
            'assessment_id' => $assessment->id,
            'category' => 'exchanges',
        ]);

        AssessmentAnswerEvidence::factory()->create([
            'assessment_id' => $assessment->id,
            'question_key' => 'exchanges.offered',
            'evidence_snippet' => 'Exchanges are free.',
        ]);
        AssessmentAnswerEvidence::factory()->create([
            'assessment_id' => $assessment->id,
            'question_key' => 'return_policy.window_days',
            'evidence_snippet' => 'Returns accepted within 30 days.',
        ]);

        $context = $this->builder()->build($assessment->fresh(), $recommendation);
        $array = $context->toArray();

        $this->assertCount(1, $array['evidence_summaries']);
        $this->assertSame('exchanges.offered', $array['evidence_summaries'][0]['question_key']);
    }

    public function test_evidence_snippets_are_truncated_and_never_contain_raw_html(): void
    {
        $assessment = Assessment::factory()->create();
        $recommendation = Recommendation::factory()->create([
            'assessment_id' => $assessment->id,
            'category' => 'exchanges',
        ]);

        $longSnippet = '<div class="policy">'.str_repeat('Exchange rules apply. ', 50).'</div>';

        AssessmentAnswerEvidence::factory()->create([
            'assessment_id' => $assessment->id,
            'question_key' => 'exchanges.offered',
            'evidence_snippet' => $longSnippet,
        ]);

        $context = $this->builder()->build($assessment->fresh(), $recommendation);
        $summary = $context->toArray()['evidence_summaries'][0]['summary'];

        $this->assertLessThanOrEqual(161, mb_strlen($summary));
        $this->assertStringNotContainsString('<div', $summary);
    }
}
