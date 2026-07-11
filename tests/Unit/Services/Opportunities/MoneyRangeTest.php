<?php

namespace Tests\Unit\Services\Opportunities;

use App\Services\Opportunities\MoneyRange;
use Error;
use InvalidArgumentException;
use Tests\TestCase;

class MoneyRangeTest extends TestCase
{
    public function test_holds_minimum_maximum_and_unit(): void
    {
        $range = new MoneyRange(minimum: 1000.0, maximum: 5000.0, unit: 'usd_per_year');

        $this->assertSame(1000.0, $range->minimum);
        $this->assertSame(5000.0, $range->maximum);
        $this->assertSame('usd_per_year', $range->unit);
    }

    public function test_properties_are_readonly(): void
    {
        $range = new MoneyRange(minimum: 1000.0, maximum: 5000.0, unit: 'usd_per_year');

        $this->expectException(Error::class);

        $range->minimum = 2000.0;
    }

    public function test_rejects_inverted_range(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MoneyRange(minimum: 5000.0, maximum: 1000.0, unit: 'usd_per_year');
    }

    public function test_allows_null_minimum_and_maximum(): void
    {
        $range = new MoneyRange(minimum: null, maximum: null, unit: 'hours_per_week');

        $this->assertNull($range->minimum);
        $this->assertNull($range->maximum);
    }

    public function test_allows_equal_minimum_and_maximum(): void
    {
        $range = new MoneyRange(minimum: 1000.0, maximum: 1000.0, unit: 'usd_per_year');

        $this->assertSame(1000.0, $range->minimum);
        $this->assertSame(1000.0, $range->maximum);
    }
}
