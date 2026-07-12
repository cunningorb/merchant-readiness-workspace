<?php

namespace Tests\Feature;

use App\Models\BenchmarkSet;
use App\Models\BenchmarkValue;
use Database\Seeders\BenchmarkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BenchmarkSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_the_illustrative_benchmark_set_with_twelve_values(): void
    {
        $this->seed(BenchmarkSeeder::class);

        $set = BenchmarkSet::where('name', 'Illustrative Returns Benchmark')->where('version', '1.0')->firstOrFail();

        $this->assertTrue($set->is_active);
        $this->assertSame('illustrative', $set->source_type);
        $this->assertSame(12, BenchmarkValue::where('benchmark_set_id', $set->id)->count());
    }

    public function test_seeds_expected_metric_and_industry_combinations(): void
    {
        $this->seed(BenchmarkSeeder::class);

        $set = BenchmarkSet::where('name', 'Illustrative Returns Benchmark')->where('version', '1.0')->firstOrFail();

        $combinations = BenchmarkValue::where('benchmark_set_id', $set->id)
            ->get(['metric_key', 'industry'])
            ->map(fn (BenchmarkValue $value) => [$value->metric_key, $value->industry])
            ->all();

        foreach (['return_window_days', 'manual_processing_hours_per_week', 'catalog_sku_count'] as $metricKey) {
            foreach (['apparel', 'footwear', 'home_goods', null] as $industry) {
                $this->assertContains([$metricKey, $industry], $combinations);
            }
        }
    }

    public function test_is_idempotent(): void
    {
        $this->seed(BenchmarkSeeder::class);
        $this->seed(BenchmarkSeeder::class);

        $this->assertSame(1, BenchmarkSet::where('name', 'Illustrative Returns Benchmark')->count());
        $this->assertSame(12, BenchmarkValue::count());
    }
}
