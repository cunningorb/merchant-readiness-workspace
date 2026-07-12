<?php

namespace Tests\Unit\Models;

use App\Models\BenchmarkSet;
use App\Models\BenchmarkValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BenchmarkValueTest extends TestCase
{
    use RefreshDatabase;

    public function test_value_belongs_to_benchmark_set(): void
    {
        $benchmarkSet = BenchmarkSet::factory()->create();
        $value = BenchmarkValue::factory()->for($benchmarkSet)->create();

        $this->assertTrue($value->benchmarkSet->is($benchmarkSet));
    }

    public function test_decimal_fields_cast_to_decimal_strings(): void
    {
        $value = BenchmarkValue::factory()->create([
            'annual_order_volume_min' => 1000,
            'annual_order_volume_max' => 2500.5,
            'minimum_value' => 5,
            'maximum_value' => 12.25,
        ]);

        $fresh = $value->fresh();

        $this->assertSame('1000.00', $fresh->annual_order_volume_min);
        $this->assertSame('2500.50', $fresh->annual_order_volume_max);
        $this->assertSame('5.00', $fresh->minimum_value);
        $this->assertSame('12.25', $fresh->maximum_value);
    }

    public function test_optional_fields_are_nullable(): void
    {
        $value = BenchmarkValue::factory()->create([
            'industry' => null,
            'platform' => null,
            'annual_order_volume_min' => null,
            'annual_order_volume_max' => null,
            'catalog_profile' => null,
            'minimum_value' => null,
            'maximum_value' => null,
            'sample_size' => null,
            'notes' => null,
        ]);

        $fresh = $value->fresh();

        $this->assertNull($fresh->industry);
        $this->assertNull($fresh->platform);
        $this->assertNull($fresh->annual_order_volume_min);
        $this->assertNull($fresh->annual_order_volume_max);
        $this->assertNull($fresh->catalog_profile);
        $this->assertNull($fresh->minimum_value);
        $this->assertNull($fresh->maximum_value);
        $this->assertNull($fresh->sample_size);
        $this->assertNull($fresh->notes);
    }

    public function test_factory_creates_valid_benchmark_value(): void
    {
        $value = BenchmarkValue::factory()->create();

        $this->assertDatabaseHas('benchmark_values', ['id' => $value->id]);
    }
}
