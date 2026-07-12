<?php

namespace Tests\Unit\Services\Benchmarks;

use App\Models\BenchmarkSet;
use App\Models\BenchmarkValue;
use App\Services\Benchmarks\BenchmarkResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BenchmarkResolverTest extends TestCase
{
    use RefreshDatabase;

    private function activeSet(array $overrides = []): BenchmarkSet
    {
        return BenchmarkSet::factory()->create(array_merge([
            'is_active' => true,
            'effective_from' => null,
            'effective_to' => null,
        ], $overrides));
    }

    private function value(BenchmarkSet $set, array $overrides = []): BenchmarkValue
    {
        return BenchmarkValue::factory()->for($set)->create(array_merge([
            'metric_key' => 'return_window_days',
            'industry' => null,
            'platform' => null,
            'annual_order_volume_min' => null,
            'annual_order_volume_max' => null,
            'catalog_profile' => null,
            'minimum_value' => 20,
            'maximum_value' => 30,
            'unit' => 'days',
            'sample_size' => null,
            'notes' => null,
        ], $overrides));
    }

    private function resolver(): BenchmarkResolver
    {
        return new BenchmarkResolver;
    }

    // --- tier 1: exact industry + volume range + platform ----------------------

    public function test_tier1_exact_industry_volume_and_platform_match_wins_over_lower_tiers(): void
    {
        $set = $this->activeSet();

        $tier1 = $this->value($set, [
            'industry' => 'apparel',
            'platform' => 'Shopify',
            'annual_order_volume_min' => 1000,
            'annual_order_volume_max' => 100000,
            'minimum_value' => 1,
            'maximum_value' => 2,
        ]);
        $this->value($set, [
            'industry' => 'apparel',
            'annual_order_volume_min' => 200000,
            'annual_order_volume_max' => 300000, // excludes context volume of 50000: tier 3 candidate only
            'minimum_value' => 3,
            'maximum_value' => 4,
        ]);
        $this->value($set, ['industry' => null, 'minimum_value' => 5, 'maximum_value' => 6]); // tier 4 candidate

        $result = $this->resolver()->resolve('return_window_days', [
            'industry' => 'apparel',
            'platform' => 'Shopify',
            'annual_order_volume' => 50000,
        ]);

        $this->assertSame($tier1->id, $result->id);
    }

    // --- tier 2: industry + volume range match, platform ignored ---------------

    public function test_tier2_industry_and_volume_match_when_platform_differs(): void
    {
        $set = $this->activeSet();

        $tier2 = $this->value($set, [
            'industry' => 'apparel',
            'platform' => 'WooCommerce',
            'annual_order_volume_min' => 1000,
            'annual_order_volume_max' => 100000,
            'minimum_value' => 1,
            'maximum_value' => 2,
        ]);
        $this->value($set, [
            'industry' => 'apparel',
            'annual_order_volume_min' => 200000,
            'annual_order_volume_max' => 300000, // excludes context volume of 50000: tier 3 candidate only
            'minimum_value' => 3,
            'maximum_value' => 4,
        ]);
        $this->value($set, ['industry' => null, 'minimum_value' => 5, 'maximum_value' => 6]); // tier 4 candidate

        $result = $this->resolver()->resolve('return_window_days', [
            'industry' => 'apparel',
            'platform' => 'Shopify',
            'annual_order_volume' => 50000,
        ]);

        $this->assertSame($tier2->id, $result->id);
    }

    public function test_tier2_matches_when_row_has_unconstrained_volume_bounds(): void
    {
        $set = $this->activeSet();

        $tier2 = $this->value($set, [
            'industry' => 'apparel',
            'platform' => 'WooCommerce',
            'annual_order_volume_min' => null,
            'annual_order_volume_max' => null,
            'minimum_value' => 1,
            'maximum_value' => 2,
        ]);
        $this->value($set, ['industry' => null, 'minimum_value' => 5, 'maximum_value' => 6]); // tier 4 candidate

        $result = $this->resolver()->resolve('return_window_days', [
            'industry' => 'apparel',
            'platform' => 'Shopify',
            'annual_order_volume' => 50000,
        ]);

        $this->assertSame($tier2->id, $result->id);
    }

    // --- tier 3: industry match only, volume and platform ignored --------------

    public function test_tier3_industry_match_when_volume_out_of_range(): void
    {
        $set = $this->activeSet();

        $tier3 = $this->value($set, [
            'industry' => 'apparel',
            'platform' => null,
            'annual_order_volume_min' => 1000,
            'annual_order_volume_max' => 2000, // context volume of 50000 falls outside this range
            'minimum_value' => 1,
            'maximum_value' => 2,
        ]);
        $this->value($set, ['industry' => null, 'minimum_value' => 5, 'maximum_value' => 6]); // tier 4 candidate

        $result = $this->resolver()->resolve('return_window_days', [
            'industry' => 'apparel',
            'platform' => 'Shopify',
            'annual_order_volume' => 50000,
        ]);

        $this->assertSame($tier3->id, $result->id);
    }

    // --- tier 4: global (industry null) -----------------------------------------

    public function test_tier4_global_row_used_when_no_industry_match(): void
    {
        $set = $this->activeSet();

        $tier4 = $this->value($set, ['industry' => null, 'minimum_value' => 21, 'maximum_value' => 30]);
        $this->value($set, ['industry' => 'footwear', 'minimum_value' => 30, 'maximum_value' => 45]);

        $result = $this->resolver()->resolve('return_window_days', [
            'industry' => 'apparel',
            'platform' => null,
            'annual_order_volume' => null,
        ]);

        $this->assertSame($tier4->id, $result->id);
    }

    public function test_tier4_global_row_used_when_context_industry_is_null(): void
    {
        $set = $this->activeSet();

        $tier4 = $this->value($set, ['industry' => null, 'minimum_value' => 21, 'maximum_value' => 30]);

        $result = $this->resolver()->resolve('return_window_days', [
            'industry' => null,
            'platform' => null,
            'annual_order_volume' => null,
        ]);

        $this->assertSame($tier4->id, $result->id);
    }

    // --- tier 5: no match -> null ------------------------------------------------

    public function test_returns_null_when_no_benchmark_value_exists_for_metric(): void
    {
        $set = $this->activeSet();
        $this->value($set, ['metric_key' => 'catalog_sku_count', 'industry' => null]);

        $result = $this->resolver()->resolve('return_window_days', [
            'industry' => null,
            'platform' => null,
            'annual_order_volume' => null,
        ]);

        $this->assertNull($result);
    }

    // --- exclusions ---------------------------------------------------------------

    public function test_excludes_rows_from_inactive_benchmark_sets(): void
    {
        $inactiveSet = $this->activeSet(['is_active' => false]);
        $this->value($inactiveSet, ['industry' => null]);

        $result = $this->resolver()->resolve('return_window_days', [
            'industry' => null,
            'platform' => null,
            'annual_order_volume' => null,
        ]);

        $this->assertNull($result);
    }

    public function test_excludes_rows_whose_effective_window_is_entirely_in_the_past(): void
    {
        $pastSet = $this->activeSet([
            'effective_from' => now()->subYear(),
            'effective_to' => now()->subMonth(),
        ]);
        $this->value($pastSet, ['industry' => null]);

        $result = $this->resolver()->resolve('return_window_days', [
            'industry' => null,
            'platform' => null,
            'annual_order_volume' => null,
        ]);

        $this->assertNull($result);
    }

    public function test_excludes_rows_whose_effective_window_is_entirely_in_the_future(): void
    {
        $futureSet = $this->activeSet([
            'effective_from' => now()->addMonth(),
            'effective_to' => now()->addYear(),
        ]);
        $this->value($futureSet, ['industry' => null]);

        $result = $this->resolver()->resolve('return_window_days', [
            'industry' => null,
            'platform' => null,
            'annual_order_volume' => null,
        ]);

        $this->assertNull($result);
    }

    public function test_includes_rows_within_a_currently_active_effective_window(): void
    {
        $currentSet = $this->activeSet([
            'effective_from' => now()->subMonth(),
            'effective_to' => now()->addMonth(),
        ]);
        $inWindow = $this->value($currentSet, ['industry' => null]);

        $result = $this->resolver()->resolve('return_window_days', [
            'industry' => null,
            'platform' => null,
            'annual_order_volume' => null,
        ]);

        $this->assertSame($inWindow->id, $result->id);
    }
}
