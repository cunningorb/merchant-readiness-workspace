<?php

namespace Tests\Unit\Services\Benchmarks;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Services\Benchmarks\MetricExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricExtractionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function answer(Assessment $assessment, string $questionKey, string $section, mixed $value): void
    {
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => $questionKey,
            'section' => $section,
            'value' => $value,
        ]);
    }

    private function extract(Assessment $assessment): array
    {
        return (new MetricExtractionService)->extract($assessment->fresh(['answers']));
    }

    private function deriveContext(Assessment $assessment): array
    {
        return (new MetricExtractionService)->deriveContext($assessment->fresh(['answers']));
    }

    // --- return_window_days -------------------------------------------------

    public function test_extracts_return_window_days_from_known_band(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'return_policy.window_days', 'return_policy', '15-30 days');

        $metrics = $this->extract($assessment);

        $this->assertSame(22.0, $metrics['return_window_days']);
    }

    public function test_return_window_days_is_null_when_answer_missing(): void
    {
        $assessment = Assessment::factory()->create();

        $metrics = $this->extract($assessment);

        $this->assertNull($metrics['return_window_days']);
    }

    public function test_return_window_days_is_null_when_band_unrecognized(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'return_policy.window_days', 'return_policy', 'Not a real band');

        $metrics = $this->extract($assessment);

        $this->assertNull($metrics['return_window_days']);
    }

    // --- manual_processing_hours_per_week ------------------------------------

    public function test_extracts_manual_processing_hours_from_shared_config_band_midpoints(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'manual_operations.weekly_hours', 'manual_operations', '5-20');

        $metrics = $this->extract($assessment);

        $this->assertSame(
            (float) config('assessment.opportunities.weekly_manual_hours_band_midpoints.5-20'),
            $metrics['manual_processing_hours_per_week']
        );
    }

    public function test_manual_processing_hours_is_null_when_answer_missing(): void
    {
        $assessment = Assessment::factory()->create();

        $metrics = $this->extract($assessment);

        $this->assertNull($metrics['manual_processing_hours_per_week']);
    }

    public function test_manual_processing_hours_is_null_when_band_unrecognized(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'manual_operations.weekly_hours', 'manual_operations', 'Not a real band');

        $metrics = $this->extract($assessment);

        $this->assertNull($metrics['manual_processing_hours_per_week']);
    }

    // --- catalog_sku_count ---------------------------------------------------

    public function test_extracts_catalog_sku_count_from_known_band(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'catalog.sku_count', 'catalog', '5,001-25,000');

        $metrics = $this->extract($assessment);

        $this->assertSame(15000.0, $metrics['catalog_sku_count']);
    }

    public function test_catalog_sku_count_is_null_when_answer_missing(): void
    {
        $assessment = Assessment::factory()->create();

        $metrics = $this->extract($assessment);

        $this->assertNull($metrics['catalog_sku_count']);
    }

    public function test_catalog_sku_count_is_null_when_band_unrecognized(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'catalog.sku_count', 'catalog', 'Not a real band');

        $metrics = $this->extract($assessment);

        $this->assertNull($metrics['catalog_sku_count']);
    }

    // --- deriveContext: industry ---------------------------------------------

    public function test_derive_context_industry_matches_apparel(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'catalog.fit_sensitive_categories', 'catalog', ['Apparel']);

        $context = $this->deriveContext($assessment);

        $this->assertSame('apparel', $context['industry']);
    }

    public function test_derive_context_industry_priority_order_prefers_apparel_over_footwear(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'catalog.fit_sensitive_categories', 'catalog', ['Footwear', 'Apparel']);

        $context = $this->deriveContext($assessment);

        $this->assertSame('apparel', $context['industry']);
    }

    public function test_derive_context_industry_priority_order_prefers_footwear_over_home_goods(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'catalog.fit_sensitive_categories', 'catalog', ['Home goods', 'Footwear']);

        $context = $this->deriveContext($assessment);

        $this->assertSame('footwear', $context['industry']);
    }

    public function test_derive_context_industry_is_null_when_no_selected_category_matches(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'catalog.fit_sensitive_categories', 'catalog', ['Accessories', 'Electronics']);

        $context = $this->deriveContext($assessment);

        $this->assertNull($context['industry']);
    }

    public function test_derive_context_industry_is_null_when_answer_missing(): void
    {
        $assessment = Assessment::factory()->create();

        $context = $this->deriveContext($assessment);

        $this->assertNull($context['industry']);
    }

    public function test_derive_context_industry_is_null_when_answer_empty(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'catalog.fit_sensitive_categories', 'catalog', []);

        $context = $this->deriveContext($assessment);

        $this->assertNull($context['industry']);
    }

    // --- deriveContext: platform ----------------------------------------------

    public function test_derive_context_platform_passthrough(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'platform.ecommerce_platform', 'platform', 'Shopify');

        $context = $this->deriveContext($assessment);

        $this->assertSame('Shopify', $context['platform']);
    }

    public function test_derive_context_platform_is_null_when_answer_missing(): void
    {
        $assessment = Assessment::factory()->create();

        $context = $this->deriveContext($assessment);

        $this->assertNull($context['platform']);
    }

    // --- deriveContext: annual_order_volume -----------------------------------

    public function test_derive_context_annual_order_volume_is_monthly_midpoint_times_twelve(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'business.monthly_order_volume', 'business', '1,000-10,000');

        $context = $this->deriveContext($assessment);

        $this->assertSame(5500.0 * 12, $context['annual_order_volume']);
    }

    public function test_derive_context_annual_order_volume_is_null_when_answer_missing(): void
    {
        $assessment = Assessment::factory()->create();

        $context = $this->deriveContext($assessment);

        $this->assertNull($context['annual_order_volume']);
    }

    public function test_derive_context_annual_order_volume_is_null_when_band_unrecognized(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'business.monthly_order_volume', 'business', 'Not a real band');

        $context = $this->deriveContext($assessment);

        $this->assertNull($context['annual_order_volume']);
    }

    public function test_derive_context_returns_expected_shape(): void
    {
        $assessment = Assessment::factory()->create();

        $context = $this->deriveContext($assessment);

        $this->assertSame(['industry', 'platform', 'annual_order_volume'], array_keys($context));
    }
}
