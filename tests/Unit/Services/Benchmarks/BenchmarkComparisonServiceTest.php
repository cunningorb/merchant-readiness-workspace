<?php

namespace Tests\Unit\Services\Benchmarks;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Models\BenchmarkSet;
use App\Models\BenchmarkValue;
use App\Services\Benchmarks\BenchmarkComparisonResult;
use App\Services\Benchmarks\BenchmarkComparisonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class BenchmarkComparisonServiceTest extends TestCase
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

    private function benchmarkSet(array $overrides = []): BenchmarkSet
    {
        return BenchmarkSet::factory()->create(array_merge([
            'is_active' => true,
            'effective_from' => null,
            'effective_to' => null,
        ], $overrides));
    }

    private function benchmarkValue(BenchmarkSet $set, array $overrides = []): BenchmarkValue
    {
        return BenchmarkValue::factory()->for($set)->create(array_merge([
            'industry' => null,
            'platform' => null,
            'annual_order_volume_min' => null,
            'annual_order_volume_max' => null,
            'catalog_profile' => null,
            'sample_size' => null,
            'notes' => null,
        ], $overrides));
    }

    /**
     * @return Collection<int, BenchmarkComparisonResult>
     */
    private function compare(Assessment $assessment): Collection
    {
        return collect(app(BenchmarkComparisonService::class)->compare($assessment->fresh(['answers'])));
    }

    public function test_produces_comparisons_in_fixed_order_with_correct_hand_verified_directions(): void
    {
        $set = $this->benchmarkSet([
            'source_type' => 'illustrative',
            'source_label' => 'Illustrative benchmark',
            'methodology' => 'Configured illustrative reference ranges.',
            'version' => '2026.1',
        ]);

        $this->benchmarkValue($set, ['metric_key' => 'return_window_days', 'minimum_value' => 30, 'maximum_value' => 45, 'unit' => 'days']);
        $this->benchmarkValue($set, ['metric_key' => 'manual_processing_hours_per_week', 'minimum_value' => 3, 'maximum_value' => 8, 'unit' => 'hours_per_week']);
        $this->benchmarkValue($set, ['metric_key' => 'catalog_sku_count', 'minimum_value' => 500, 'maximum_value' => 10000, 'unit' => 'sku_count']);

        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'return_policy.window_days', 'return_policy', '15-30 days'); // 22, below 30-45
        $this->answer($assessment, 'manual_operations.weekly_hours', 'manual_operations', '5-20'); // 12, above 3-8
        $this->answer($assessment, 'catalog.sku_count', 'catalog', '500-5,000'); // 2750, within 500-10000

        $results = $this->compare($assessment);

        $this->assertCount(3, $results);
        $this->assertSame(
            ['return_window_days', 'manual_processing_hours_per_week', 'catalog_sku_count'],
            $results->pluck('metricKey')->all()
        );

        $returnWindow = $results->get(0);
        $this->assertSame('Return window', $returnWindow->label);
        $this->assertSame(22.0, $returnWindow->merchantValue);
        $this->assertSame(30.0, $returnWindow->range->minimum);
        $this->assertSame(45.0, $returnWindow->range->maximum);
        $this->assertSame('days', $returnWindow->range->unit);
        $this->assertStringContainsString('below', $returnWindow->interpretation);
        $this->assertSame('illustrative', $returnWindow->sourceType->value);
        $this->assertSame('Illustrative benchmark', $returnWindow->sourceLabel);
        $this->assertSame('Configured illustrative reference ranges.', $returnWindow->methodology);
        $this->assertSame('2026.1', $returnWindow->benchmarkVersion);
        $this->assertSame($set->id, $returnWindow->benchmarkSetId);

        $manualHours = $results->get(1);
        $this->assertSame(12.0, $manualHours->merchantValue);
        $this->assertStringContainsString('above', $manualHours->interpretation);

        $catalog = $results->get(2);
        $this->assertSame(2750.0, $catalog->merchantValue);
        $this->assertStringContainsString('within', $catalog->interpretation);
    }

    public function test_skips_metric_when_answer_is_missing(): void
    {
        $set = $this->benchmarkSet();
        $this->benchmarkValue($set, ['metric_key' => 'return_window_days', 'minimum_value' => 30, 'maximum_value' => 45, 'unit' => 'days']);
        $this->benchmarkValue($set, ['metric_key' => 'manual_processing_hours_per_week', 'minimum_value' => 3, 'maximum_value' => 8, 'unit' => 'hours_per_week']);
        $this->benchmarkValue($set, ['metric_key' => 'catalog_sku_count', 'minimum_value' => 500, 'maximum_value' => 10000, 'unit' => 'sku_count']);

        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'manual_operations.weekly_hours', 'manual_operations', '5-20');
        $this->answer($assessment, 'catalog.sku_count', 'catalog', '500-5,000');
        // return_policy.window_days intentionally unanswered

        $results = $this->compare($assessment);

        $this->assertCount(2, $results);
        $this->assertSame(
            ['manual_processing_hours_per_week', 'catalog_sku_count'],
            $results->pluck('metricKey')->all()
        );
    }

    public function test_skips_metric_when_answer_band_is_unrecognized(): void
    {
        $set = $this->benchmarkSet();
        $this->benchmarkValue($set, ['metric_key' => 'return_window_days', 'minimum_value' => 30, 'maximum_value' => 45, 'unit' => 'days']);

        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'return_policy.window_days', 'return_policy', 'Not a real band');

        $results = $this->compare($assessment);

        $this->assertCount(0, $results);
    }

    public function test_skips_metric_when_no_benchmark_resolves(): void
    {
        // No BenchmarkSet/BenchmarkValue rows exist at all.
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'return_policy.window_days', 'return_policy', '15-30 days');
        $this->answer($assessment, 'manual_operations.weekly_hours', 'manual_operations', '5-20');
        $this->answer($assessment, 'catalog.sku_count', 'catalog', '500-5,000');

        $results = $this->compare($assessment);

        $this->assertCount(0, $results);
    }

    public function test_skips_metric_when_resolved_benchmark_has_no_usable_range(): void
    {
        $set = $this->benchmarkSet();
        // A benchmark row exists for the metric, but its range bounds are null
        // (schema allows this); it must be treated the same as "no benchmark".
        $this->benchmarkValue($set, [
            'metric_key' => 'return_window_days',
            'minimum_value' => null,
            'maximum_value' => null,
            'unit' => 'days',
        ]);

        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'return_policy.window_days', 'return_policy', '15-30 days');

        $results = $this->compare($assessment);

        $this->assertCount(0, $results);
    }

    public function test_skips_only_the_metric_with_no_matching_benchmark_while_keeping_others(): void
    {
        $set = $this->benchmarkSet();
        // Only a benchmark for catalog_sku_count exists.
        $this->benchmarkValue($set, ['metric_key' => 'catalog_sku_count', 'minimum_value' => 500, 'maximum_value' => 10000, 'unit' => 'sku_count']);

        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'return_policy.window_days', 'return_policy', '15-30 days');
        $this->answer($assessment, 'catalog.sku_count', 'catalog', '500-5,000');

        $results = $this->compare($assessment);

        $this->assertCount(1, $results);
        $this->assertSame('catalog_sku_count', $results->first()->metricKey);
    }

    public function test_interpretation_never_uses_evaluative_or_causal_language(): void
    {
        $set = $this->benchmarkSet();
        $this->benchmarkValue($set, ['metric_key' => 'return_window_days', 'minimum_value' => 30, 'maximum_value' => 45, 'unit' => 'days']);
        $this->benchmarkValue($set, ['metric_key' => 'manual_processing_hours_per_week', 'minimum_value' => 3, 'maximum_value' => 8, 'unit' => 'hours_per_week']);
        $this->benchmarkValue($set, ['metric_key' => 'catalog_sku_count', 'minimum_value' => 500, 'maximum_value' => 10000, 'unit' => 'sku_count']);

        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'return_policy.window_days', 'return_policy', '15-30 days');
        $this->answer($assessment, 'manual_operations.weekly_hours', 'manual_operations', '5-20');
        $this->answer($assessment, 'catalog.sku_count', 'catalog', '500-5,000');

        $results = $this->compare($assessment);

        $this->assertGreaterThan(0, $results->count());

        foreach ($results as $result) {
            $this->assertStringNotContainsStringIgnoringCase('profit', $result->interpretation);
            $this->assertStringNotContainsStringIgnoringCase('guaranteed', $result->interpretation);
            $this->assertStringNotContainsStringIgnoringCase('proven', $result->interpretation);
            $this->assertStringNotContainsStringIgnoringCase('better than', $result->interpretation);
            $this->assertStringNotContainsStringIgnoringCase('worse than', $result->interpretation);
        }
    }
}
