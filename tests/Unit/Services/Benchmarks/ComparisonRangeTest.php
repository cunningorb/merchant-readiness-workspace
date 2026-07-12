<?php

namespace Tests\Unit\Services\Benchmarks;

use App\Services\Benchmarks\ComparisonRange;
use Error;
use InvalidArgumentException;
use Tests\TestCase;

class ComparisonRangeTest extends TestCase
{
    public function test_holds_minimum_maximum_and_unit(): void
    {
        $range = new ComparisonRange(minimum: 10.0, maximum: 25.0, unit: 'percent');

        $this->assertSame(10.0, $range->minimum);
        $this->assertSame(25.0, $range->maximum);
        $this->assertSame('percent', $range->unit);
    }

    public function test_properties_are_readonly(): void
    {
        $range = new ComparisonRange(minimum: 10.0, maximum: 25.0, unit: 'percent');

        $this->expectException(Error::class);

        $range->minimum = 20.0;
    }

    public function test_rejects_inverted_range(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ComparisonRange(minimum: 25.0, maximum: 10.0, unit: 'percent');
    }

    public function test_allows_null_minimum_and_maximum(): void
    {
        $range = new ComparisonRange(minimum: null, maximum: null, unit: 'days');

        $this->assertNull($range->minimum);
        $this->assertNull($range->maximum);
    }

    public function test_allows_null_minimum_with_maximum_present(): void
    {
        $range = new ComparisonRange(minimum: null, maximum: 25.0, unit: 'percent');

        $this->assertNull($range->minimum);
        $this->assertSame(25.0, $range->maximum);
    }

    public function test_allows_null_maximum_with_minimum_present(): void
    {
        $range = new ComparisonRange(minimum: 10.0, maximum: null, unit: 'percent');

        $this->assertSame(10.0, $range->minimum);
        $this->assertNull($range->maximum);
    }

    public function test_allows_equal_minimum_and_maximum(): void
    {
        $range = new ComparisonRange(minimum: 10.0, maximum: 10.0, unit: 'percent');

        $this->assertSame(10.0, $range->minimum);
        $this->assertSame(10.0, $range->maximum);
    }
}
