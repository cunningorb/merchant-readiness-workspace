<?php

namespace Tests\Feature;

use App\Models\BenchmarkSet;
use App\Models\BenchmarkValue;
use Database\Seeders\BenchmarkSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A subclass that fails partway through seeding the twelve benchmark
 * values, simulating a process crash mid-seed.
 */
class CrashingBenchmarkSeeder extends BenchmarkSeeder
{
    protected function values(): array
    {
        $values = parent::values();
        $values[6]['unit'] = null; // benchmark_values.unit is NOT NULL

        return $values;
    }
}

/**
 * A subclass that simulates the race window between the alreadySeeded()
 * check and the transaction's insert: it always reports "not yet seeded",
 * even when another process has already inserted the row.
 */
class RacingBenchmarkSeeder extends BenchmarkSeeder
{
    protected function alreadySeeded(): bool
    {
        return false;
    }
}

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

    public function test_a_crash_mid_seed_leaves_no_partial_rows_and_is_fully_recoverable_on_next_boot(): void
    {
        try {
            $this->seed(CrashingBenchmarkSeeder::class);
            $this->fail('Expected the crashing seeder to throw.');
        } catch (QueryException $exception) {
            // expected: simulates the process dying mid-seed
        }

        $this->assertSame(0, BenchmarkSet::count());
        $this->assertSame(0, BenchmarkValue::count());

        // Next boot retries with the real seeder and fully recovers.
        $this->seed(BenchmarkSeeder::class);

        $this->assertSame(1, BenchmarkSet::where('name', 'Illustrative Returns Benchmark')->where('version', '1.0')->count());
        $this->assertSame(12, BenchmarkValue::count());
    }

    public function test_a_concurrent_boot_racing_past_the_already_seeded_check_does_not_duplicate_the_set(): void
    {
        // Simulates another container having already committed the row
        // between this process's alreadySeeded() check and its insert.
        DB::table('benchmark_sets')->insert([
            'name' => 'Illustrative Returns Benchmark',
            'version' => '1.0',
            'source_type' => 'illustrative',
            'source_label' => 'Illustrative benchmark',
            'methodology' => 'Existing row created by a concurrent boot.',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seed(RacingBenchmarkSeeder::class);

        $this->assertSame(1, BenchmarkSet::where('name', 'Illustrative Returns Benchmark')->where('version', '1.0')->count());
        $this->assertSame(0, BenchmarkValue::count());
    }

    public function test_duplicate_name_and_version_violates_the_unique_index(): void
    {
        $this->expectException(QueryException::class);

        DB::table('benchmark_sets')->insert([
            'name' => 'Duplicate Set',
            'version' => '1.0',
            'source_type' => 'illustrative',
            'source_label' => 'Illustrative benchmark',
            'methodology' => 'First.',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('benchmark_sets')->insert([
            'name' => 'Duplicate Set',
            'version' => '1.0',
            'source_type' => 'illustrative',
            'source_label' => 'Illustrative benchmark',
            'methodology' => 'Second.',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
