<?php

namespace Tests\Unit\Services\Benchmarks;

use App\Enums\BenchmarkSourceType;
use App\Services\Benchmarks\BenchmarkComparisonResult;
use App\Services\Benchmarks\ComparisonRange;
use Error;
use Tests\TestCase;

class BenchmarkComparisonResultTest extends TestCase
{
    public function test_holds_constructed_values(): void
    {
        $range = new ComparisonRange(minimum: 5.0, maximum: 15.0, unit: 'percent');

        $result = new BenchmarkComparisonResult(
            metricKey: 'return_rate',
            label: 'Return Rate',
            merchantValue: 22.5,
            range: $range,
            interpretation: 'Above typical range for this industry.',
            sourceType: BenchmarkSourceType::Illustrative,
            sourceLabel: 'Illustrative industry dataset',
            methodology: 'Derived from aggregated illustrative figures.',
            benchmarkVersion: '2026.1',
        );

        $this->assertSame('return_rate', $result->metricKey);
        $this->assertSame('Return Rate', $result->label);
        $this->assertSame(22.5, $result->merchantValue);
        $this->assertSame($range, $result->range);
        $this->assertSame('Above typical range for this industry.', $result->interpretation);
        $this->assertSame(BenchmarkSourceType::Illustrative, $result->sourceType);
        $this->assertSame('Illustrative industry dataset', $result->sourceLabel);
        $this->assertSame('Derived from aggregated illustrative figures.', $result->methodology);
        $this->assertSame('2026.1', $result->benchmarkVersion);
    }

    public function test_properties_are_readonly(): void
    {
        $result = new BenchmarkComparisonResult(
            metricKey: 'return_rate',
            label: 'Return Rate',
            merchantValue: 22.5,
            range: new ComparisonRange(minimum: 5.0, maximum: 15.0, unit: 'percent'),
            interpretation: 'Above typical range for this industry.',
            sourceType: BenchmarkSourceType::Illustrative,
            sourceLabel: 'Illustrative industry dataset',
            methodology: 'Derived from aggregated illustrative figures.',
            benchmarkVersion: '2026.1',
        );

        $this->expectException(Error::class);

        $result->merchantValue = 30.0;
    }

    public function test_to_array_flattens_range_and_source_type(): void
    {
        $result = new BenchmarkComparisonResult(
            metricKey: 'return_rate',
            label: 'Return Rate',
            merchantValue: 22.5,
            range: new ComparisonRange(minimum: 5.0, maximum: 15.0, unit: 'percent'),
            interpretation: 'Above typical range for this industry.',
            sourceType: BenchmarkSourceType::Illustrative,
            sourceLabel: 'Illustrative industry dataset',
            methodology: 'Derived from aggregated illustrative figures.',
            benchmarkVersion: '2026.1',
        );

        $this->assertSame([
            'metric_key' => 'return_rate',
            'label' => 'Return Rate',
            'merchant_value' => 22.5,
            'minimum_value' => 5.0,
            'maximum_value' => 15.0,
            'unit' => 'percent',
            'interpretation' => 'Above typical range for this industry.',
            'source_type' => 'illustrative',
            'source_label' => 'Illustrative industry dataset',
            'methodology' => 'Derived from aggregated illustrative figures.',
            'benchmark_version' => '2026.1',
        ], $result->toArray());
    }

    public function test_to_array_tolerates_null_range_bounds(): void
    {
        $result = new BenchmarkComparisonResult(
            metricKey: 'return_rate',
            label: 'Return Rate',
            merchantValue: 22.5,
            range: new ComparisonRange(minimum: null, maximum: null, unit: 'percent'),
            interpretation: 'No comparable range available.',
            sourceType: BenchmarkSourceType::Configured,
            sourceLabel: 'Configured benchmark',
            methodology: 'Manually configured by the operations team.',
            benchmarkVersion: '2026.1',
        );

        $array = $result->toArray();

        $this->assertNull($array['minimum_value']);
        $this->assertNull($array['maximum_value']);
    }
}
