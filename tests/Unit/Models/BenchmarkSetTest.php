<?php

namespace Tests\Unit\Models;

use App\Models\BenchmarkSet;
use App\Models\BenchmarkValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BenchmarkSetTest extends TestCase
{
    use RefreshDatabase;

    public function test_benchmark_set_has_many_values(): void
    {
        $benchmarkSet = BenchmarkSet::factory()->create();
        $value = BenchmarkValue::factory()->for($benchmarkSet)->create();

        $this->assertTrue($benchmarkSet->values->contains($value));
    }

    public function test_is_active_casts_to_boolean(): void
    {
        $benchmarkSet = BenchmarkSet::factory()->create(['is_active' => 1]);

        $this->assertIsBool($benchmarkSet->fresh()->is_active);
        $this->assertTrue($benchmarkSet->fresh()->is_active);
    }

    public function test_effective_from_and_effective_to_cast_to_datetime(): void
    {
        $benchmarkSet = BenchmarkSet::factory()->create([
            'effective_from' => '2026-01-01 00:00:00',
            'effective_to' => '2026-12-31 00:00:00',
        ]);

        $fresh = $benchmarkSet->fresh();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $fresh->effective_from);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $fresh->effective_to);
    }

    public function test_factory_creates_valid_benchmark_set(): void
    {
        $benchmarkSet = BenchmarkSet::factory()->create();

        $this->assertDatabaseHas('benchmark_sets', ['id' => $benchmarkSet->id]);
    }
}
