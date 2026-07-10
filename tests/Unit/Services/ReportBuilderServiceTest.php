<?php

namespace Tests\Unit\Services;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
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
}
