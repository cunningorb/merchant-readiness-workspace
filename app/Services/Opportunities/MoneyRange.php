<?php

namespace App\Services\Opportunities;

use InvalidArgumentException;

final readonly class MoneyRange
{
    public function __construct(
        public ?float $minimum,
        public ?float $maximum,
        public string $unit,
    ) {
        if ($this->minimum !== null && $this->maximum !== null && $this->minimum > $this->maximum) {
            throw new InvalidArgumentException('MoneyRange minimum cannot be greater than maximum.');
        }
    }
}
