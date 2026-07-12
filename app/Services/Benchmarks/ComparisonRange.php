<?php

namespace App\Services\Benchmarks;

use InvalidArgumentException;

final readonly class ComparisonRange
{
    public function __construct(
        public ?float $minimum,
        public ?float $maximum,
        public string $unit,
    ) {
        if ($this->minimum !== null && $this->maximum !== null && $this->minimum > $this->maximum) {
            throw new InvalidArgumentException('ComparisonRange minimum cannot be greater than maximum.');
        }
    }
}
