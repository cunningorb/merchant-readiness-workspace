<?php

namespace Tests\Unit\Services;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Models\AssessmentOpportunity;
use App\Models\Merchant;
use App\Models\Recommendation;
use App\Services\ReportBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportBuilderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_report_with_token_and_published_at(): void
    {
        $assessment = Assessment::factory()->create([
            'overall_score' => 72,
            'overall_tier' => 'Established',
            'section_scores' => ['return_policy' => ['score' => 72, 'tier' => 'Established']],
        ]);

        $report = app(ReportBuilderService::class)->createForAssessment($assessment);

        $this->assertNotEmpty($report->token);
        $this->assertNotNull($report->published_at);
        $this->assertTrue($report->assessment->is($assessment));
        $this->assertDatabaseCount('reports', 1);
    }

    public function test_build_payload_includes_score_and_recommendation_data(): void
    {
        $assessment = Assessment::factory()->create([
            'overall_score' => 72,
            'overall_tier' => 'Established',
            'section_scores' => [
                'return_policy' => ['score' => 60, 'tier' => 'Developing'],
                'exchanges' => ['score' => 90, 'tier' => 'Advanced'],
            ],
        ]);
        Recommendation::factory()->for($assessment)->create([
            'title' => 'Extend your return window',
            'category' => 'return_policy',
            'priority' => 'high',
        ]);

        $service = app(ReportBuilderService::class);
        $report = $service->createForAssessment($assessment);
        $payload = $service->buildPayload($report);

        $this->assertSame(72, $payload['assessment']['overall_score']);
        $this->assertSame('Established', $payload['assessment']['overall_tier']);
        $this->assertSame(['return_policy', 'exchanges'], array_keys($payload['assessment']['ranked_sections']));
        $this->assertCount(1, $payload['recommendations']);
        $this->assertSame('Extend your return window', $payload['recommendations'][0]['title']);
        $this->assertNotNull($payload['published_at']);
    }

    public function test_build_payload_includes_present_optional_profile_fields(): void
    {
        $merchant = Merchant::factory()->create(['company_name' => 'Northwind Supply']);
        $assessment = Assessment::factory()->for($merchant)->create();

        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'business.monthly_order_volume',
            'section' => 'business',
            'value' => '1,000-10,000',
        ]);
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'catalog.sku_count',
            'section' => 'catalog',
            'value' => '500-5,000',
        ]);
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'platform.ecommerce_platform',
            'section' => 'platform',
            'value' => 'Shopify',
        ]);

        $service = app(ReportBuilderService::class);
        $report = $service->createForAssessment($assessment);
        $payload = $service->buildPayload($report);

        $this->assertSame([
            'company_name' => 'Northwind Supply',
            'monthly_order_volume' => '1,000-10,000',
            'sku_count' => '500-5,000',
            'ecommerce_platform' => 'Shopify',
        ], $payload['merchant']);
    }

    public function test_build_payload_omits_absent_optional_profile_fields(): void
    {
        $merchant = Merchant::factory()->create(['company_name' => 'Northwind Supply']);
        $assessment = Assessment::factory()->for($merchant)->create();

        $service = app(ReportBuilderService::class);
        $report = $service->createForAssessment($assessment);
        $payload = $service->buildPayload($report);

        $this->assertSame(['company_name' => 'Northwind Supply'], $payload['merchant']);
    }

    // --- heroOpportunity ---------------------------------------------------

    public function test_build_payload_hero_opportunity_is_top_ranked_with_all_fields(): void
    {
        $assessment = Assessment::factory()->create();

        AssessmentOpportunity::factory()->for($assessment)->create([
            'type' => AssessmentOpportunity::TYPE_MANUAL_WORK_SAVINGS,
            'title' => 'Manual work title',
            'summary' => 'Manual work summary',
            'minimum_value' => 10,
            'maximum_value' => 20,
            'unit' => 'hours_per_week',
            'confidence' => 'medium',
            'effort' => 'medium',
            'assumptions' => ['x' => 1],
            'evidence' => ['inputs' => [], 'source_answer_keys' => [], 'why' => []],
            'formula_version' => '1.0',
            'sort_order' => 1,
        ]);
        AssessmentOpportunity::factory()->for($assessment)->create([
            'type' => AssessmentOpportunity::TYPE_RETAINED_REVENUE,
            'title' => 'Retained revenue title',
            'summary' => 'Retained revenue summary',
            'minimum_value' => 1000,
            'maximum_value' => 2000,
            'unit' => 'usd_per_year',
            'confidence' => 'medium',
            'effort' => 'medium',
            'assumptions' => ['y' => 2],
            'evidence' => ['inputs' => ['a' => 1], 'source_answer_keys' => ['business.monthly_order_volume'], 'why' => ['because']],
            'formula_version' => '1.0',
            'sort_order' => 0,
        ]);

        $service = app(ReportBuilderService::class);
        $report = $service->createForAssessment($assessment);
        $payload = $service->buildPayload($report);

        $this->assertSame('monetary', $payload['heroOpportunity']['kind']);
        $this->assertSame('retained_revenue', $payload['heroOpportunity']['type']);
        $this->assertSame('Retained revenue title', $payload['heroOpportunity']['title']);
        $this->assertSame('Retained revenue summary', $payload['heroOpportunity']['summary']);
        $this->assertEquals(1000.0, $payload['heroOpportunity']['minimum_value']);
        $this->assertEquals(2000.0, $payload['heroOpportunity']['maximum_value']);
        $this->assertSame('usd_per_year', $payload['heroOpportunity']['unit']);
        $this->assertSame('medium', $payload['heroOpportunity']['confidence']);
        $this->assertSame('medium', $payload['heroOpportunity']['effort']);
        $this->assertSame(['y' => 2], $payload['heroOpportunity']['assumptions']);
        $this->assertSame(['a' => 1], $payload['heroOpportunity']['evidence']['inputs']);
        $this->assertSame('1.0', $payload['heroOpportunity']['formula_version']);
    }

    public function test_build_payload_hero_opportunity_is_quantified_for_non_monetary_units(): void
    {
        $assessment = Assessment::factory()->create();

        AssessmentOpportunity::factory()->for($assessment)->create([
            'type' => AssessmentOpportunity::TYPE_MANUAL_WORK_SAVINGS,
            'unit' => 'hours_per_week',
            'sort_order' => 0,
        ]);

        $service = app(ReportBuilderService::class);
        $report = $service->createForAssessment($assessment);
        $payload = $service->buildPayload($report);

        $this->assertSame('quantified', $payload['heroOpportunity']['kind']);
    }

    public function test_build_payload_hero_opportunity_falls_back_when_no_opportunities_exist(): void
    {
        $assessment = Assessment::factory()->create();
        Recommendation::factory()->for($assessment)->count(2)->create();

        $service = app(ReportBuilderService::class);
        $report = $service->createForAssessment($assessment);
        $payload = $service->buildPayload($report);

        $this->assertSame([
            'kind' => 'fallback',
            'title' => '2 operational changes are likely to improve your returns process',
            'summary' => $payload['heroOpportunity']['summary'],
            'confidence' => 'low',
        ], $payload['heroOpportunity']);
        $this->assertStringNotContainsString('$', $payload['heroOpportunity']['summary']);

        // existing keys are intact (backwards compatibility)
        $this->assertArrayHasKey('merchant', $payload);
        $this->assertArrayHasKey('assessment', $payload);
        $this->assertArrayHasKey('recommendations', $payload);
        $this->assertArrayHasKey('published_at', $payload);
    }

    public function test_build_payload_hero_opportunity_fallback_uses_targeted_changes_title_when_no_recommendations(): void
    {
        $assessment = Assessment::factory()->create();

        $service = app(ReportBuilderService::class);
        $report = $service->createForAssessment($assessment);
        $payload = $service->buildPayload($report);

        $this->assertSame('Targeted changes are likely to improve your returns process', $payload['heroOpportunity']['title']);
        $this->assertStringNotContainsString('$', $payload['heroOpportunity']['summary']);
    }

    // --- supportingMetrics ---------------------------------------------------

    public function test_build_payload_supporting_metrics_includes_up_to_three_with_score_last(): void
    {
        $assessment = Assessment::factory()->create(['overall_score' => 55]);

        AssessmentOpportunity::factory()->for($assessment)->create([
            'type' => AssessmentOpportunity::TYPE_RETAINED_REVENUE,
            'minimum_value' => 1000,
            'maximum_value' => 2000,
            'unit' => 'usd_per_year',
            'sort_order' => 0,
        ]);
        AssessmentOpportunity::factory()->for($assessment)->create([
            'type' => AssessmentOpportunity::TYPE_MANUAL_WORK_SAVINGS,
            'minimum_value' => 5,
            'maximum_value' => 10,
            'unit' => 'hours_per_week',
            'sort_order' => 1,
        ]);
        AssessmentOpportunity::factory()->for($assessment)->create([
            'type' => AssessmentOpportunity::TYPE_SUPPORT_CONTACT_REDUCTION,
            'minimum_value' => 20,
            'maximum_value' => 30,
            'unit' => 'contacts_per_month',
            'sort_order' => 2,
        ]);

        $service = app(ReportBuilderService::class);
        $report = $service->createForAssessment($assessment);
        $payload = $service->buildPayload($report);

        $this->assertCount(3, $payload['supportingMetrics']);
        $this->assertSame(
            ['retained_revenue', 'manual_work_savings', 'overall_score'],
            array_column($payload['supportingMetrics'], 'key')
        );
        $this->assertSame('score', $payload['supportingMetrics'][2]['source']);
        $this->assertSame(55, $payload['supportingMetrics'][2]['value']);
    }

    public function test_build_payload_supporting_metrics_uses_support_contact_reduction_as_filler(): void
    {
        $assessment = Assessment::factory()->create(['overall_score' => 40]);

        AssessmentOpportunity::factory()->for($assessment)->create([
            'type' => AssessmentOpportunity::TYPE_RETAINED_REVENUE,
            'minimum_value' => 1000,
            'maximum_value' => 2000,
            'unit' => 'usd_per_year',
            'sort_order' => 0,
        ]);
        AssessmentOpportunity::factory()->for($assessment)->create([
            'type' => AssessmentOpportunity::TYPE_SUPPORT_CONTACT_REDUCTION,
            'minimum_value' => 20,
            'maximum_value' => 30,
            'unit' => 'contacts_per_month',
            'sort_order' => 1,
        ]);

        $service = app(ReportBuilderService::class);
        $report = $service->createForAssessment($assessment);
        $payload = $service->buildPayload($report);

        $this->assertSame(
            ['retained_revenue', 'support_contact_reduction', 'overall_score'],
            array_column($payload['supportingMetrics'], 'key')
        );
    }

    public function test_build_payload_supporting_metrics_is_just_score_when_no_opportunities(): void
    {
        $assessment = Assessment::factory()->create(['overall_score' => 30]);

        $service = app(ReportBuilderService::class);
        $report = $service->createForAssessment($assessment);
        $payload = $service->buildPayload($report);

        $this->assertCount(1, $payload['supportingMetrics']);
        $this->assertSame('overall_score', $payload['supportingMetrics'][0]['key']);
        $this->assertSame('score', $payload['supportingMetrics'][0]['source']);
    }

    // --- topRecommendations / remainingRecommendations ------------------------

    public function test_build_payload_splits_ranked_recommendations_into_top_three_and_remaining(): void
    {
        $assessment = Assessment::factory()->create();

        AssessmentOpportunity::factory()->for($assessment)->create([
            'type' => AssessmentOpportunity::TYPE_RETAINED_REVENUE,
            'confidence' => 'medium',
            'sort_order' => 0,
        ]);
        AssessmentOpportunity::factory()->for($assessment)->create([
            'type' => AssessmentOpportunity::TYPE_MANUAL_WORK_SAVINGS,
            'confidence' => 'medium',
            'sort_order' => 1,
        ]);
        AssessmentOpportunity::factory()->for($assessment)->create([
            'type' => AssessmentOpportunity::TYPE_SUPPORT_CONTACT_REDUCTION,
            'confidence' => 'medium',
            'sort_order' => 2,
        ]);

        Recommendation::factory()->for($assessment)->create(['category' => 'platform', 'priority' => 'low', 'title' => 'Platform rec']);
        Recommendation::factory()->for($assessment)->create(['category' => 'exchanges', 'priority' => 'low', 'title' => 'Exchanges rec']);
        Recommendation::factory()->for($assessment)->create(['category' => 'manual_operations', 'priority' => 'low', 'title' => 'Manual ops rec']);
        Recommendation::factory()->for($assessment)->create(['category' => 'return_policy', 'priority' => 'low', 'title' => 'Policy rec']);

        $service = app(ReportBuilderService::class);
        $report = $service->createForAssessment($assessment);
        $payload = $service->buildPayload($report);

        $this->assertCount(3, $payload['topRecommendations']);
        $this->assertCount(1, $payload['remainingRecommendations']);
        $this->assertSame(
            ['Exchanges rec', 'Manual ops rec', 'Policy rec'],
            array_column($payload['topRecommendations'], 'title')
        );
        $this->assertSame(['Platform rec'], array_column($payload['remainingRecommendations'], 'title'));
        $this->assertSame('retained_revenue', $payload['topRecommendations'][0]['opportunity_type']);
        $this->assertNull($payload['remainingRecommendations'][0]['opportunity_type']);

        // legacy key is unaffected
        $this->assertCount(4, $payload['recommendations']);
        $this->assertArrayNotHasKey('opportunity_type', $payload['recommendations'][0]);
    }

    // --- calculationExplanations ---------------------------------------------

    public function test_build_payload_calculation_explanations_are_sourced_from_persisted_rows(): void
    {
        $assessment = Assessment::factory()->create();

        AssessmentOpportunity::factory()->for($assessment)->create([
            'type' => AssessmentOpportunity::TYPE_RETAINED_REVENUE,
            'title' => 'Retained revenue title',
            'confidence' => 'medium',
            'formula_version' => '1.0',
            'assumptions' => [
                'order_volume_band_midpoint' => ['value' => 5500, 'source' => 'merchant_answer', 'band' => '1,000-10,000'],
                'assumed_average_order_value' => ['value' => 85.0, 'source' => 'configured_assumption'],
            ],
            'evidence' => [
                'inputs' => ['order_volume_band' => '1,000-10,000'],
                'source_answer_keys' => ['business.monthly_order_volume'],
                'why' => ['Estimated annual orders...'],
            ],
        ]);

        $service = app(ReportBuilderService::class);
        $report = $service->createForAssessment($assessment);
        $payload = $service->buildPayload($report);

        $explanation = $payload['calculationExplanations']['retained_revenue'];
        $this->assertSame('Retained revenue title', $explanation['title']);
        $this->assertSame(['order_volume_band' => '1,000-10,000'], $explanation['inputs']);
        $this->assertArrayHasKey('assumed_average_order_value', $explanation['assumptions']);
        $this->assertArrayNotHasKey('order_volume_band_midpoint', $explanation['assumptions']);
        $this->assertSame('medium', $explanation['confidence']);
        $this->assertSame('1.0', $explanation['formula_version']);
        $this->assertNotEmpty($explanation['formula_description']);
    }

    public function test_build_payload_calculation_explanations_empty_when_no_opportunities(): void
    {
        $assessment = Assessment::factory()->create();

        $service = app(ReportBuilderService::class);
        $report = $service->createForAssessment($assessment);
        $payload = $service->buildPayload($report);

        $this->assertSame([], $payload['calculationExplanations']);
    }

    // --- actionPlan -----------------------------------------------------------

    public function test_build_payload_action_plan_buckets_recommendations_by_priority(): void
    {
        $assessment = Assessment::factory()->create();

        Recommendation::factory()->for($assessment)->create(['category' => 'exchanges', 'priority' => 'high', 'title' => 'High 1']);
        Recommendation::factory()->for($assessment)->create(['category' => 'manual_operations', 'priority' => 'high', 'title' => 'High 2']);
        Recommendation::factory()->for($assessment)->create(['category' => 'return_policy', 'priority' => 'medium', 'title' => 'Medium 1']);
        Recommendation::factory()->for($assessment)->create(['category' => 'platform', 'priority' => 'low', 'title' => 'Low 1']);

        $service = app(ReportBuilderService::class);
        $report = $service->createForAssessment($assessment);
        $payload = $service->buildPayload($report);

        $this->assertSame(['High 1', 'High 2'], $payload['actionPlan']['this_week']);
        $this->assertSame(['Medium 1', 'Low 1'], $payload['actionPlan']['plan_next']);
    }

    public function test_build_payload_action_plan_caps_this_week_at_three_high_priority_items(): void
    {
        $assessment = Assessment::factory()->create();

        Recommendation::factory()->for($assessment)->create(['category' => 'exchanges', 'priority' => 'high', 'title' => 'A']);
        Recommendation::factory()->for($assessment)->create(['category' => 'manual_operations', 'priority' => 'high', 'title' => 'B']);
        Recommendation::factory()->for($assessment)->create(['category' => 'return_policy', 'priority' => 'high', 'title' => 'C']);
        Recommendation::factory()->for($assessment)->create(['category' => 'platform', 'priority' => 'high', 'title' => 'D']);

        $service = app(ReportBuilderService::class);
        $report = $service->createForAssessment($assessment);
        $payload = $service->buildPayload($report);

        $this->assertCount(3, $payload['actionPlan']['this_week']);
        $this->assertSame(['A', 'B', 'C'], $payload['actionPlan']['this_week']);
        $this->assertSame(['D'], $payload['actionPlan']['plan_next']);
    }

    public function test_build_payload_action_plan_is_empty_when_no_recommendations(): void
    {
        $assessment = Assessment::factory()->create();

        $service = app(ReportBuilderService::class);
        $report = $service->createForAssessment($assessment);
        $payload = $service->buildPayload($report);

        $this->assertSame(['this_week' => [], 'plan_next' => []], $payload['actionPlan']);
    }
}
