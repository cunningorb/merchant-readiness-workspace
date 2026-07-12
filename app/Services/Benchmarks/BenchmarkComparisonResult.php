<?php

namespace App\Services\Benchmarks;

use App\Enums\BenchmarkSourceType;

final readonly class BenchmarkComparisonResult
{
    public function __construct(
        public string $metricKey,
        public string $label,
        public float $merchantValue,
        public ComparisonRange $range,
        public string $interpretation,
        public BenchmarkSourceType $sourceType,
        public string $sourceLabel,
        public string $methodology,
        public string $benchmarkVersion,
        public ?int $benchmarkSetId = null,
    ) {}

    /**
     * @return array{metric_key: string, label: string, merchant_value: float, minimum_value: ?float, maximum_value: ?float, unit: string, interpretation: string, source_type: string, source_label: string, methodology: string, benchmark_version: string}
     */
    public function toArray(): array
    {
        return [
            'metric_key' => $this->metricKey,
            'label' => $this->label,
            'merchant_value' => $this->merchantValue,
            'minimum_value' => $this->range->minimum,
            'maximum_value' => $this->range->maximum,
            'unit' => $this->range->unit,
            'interpretation' => $this->interpretation,
            'source_type' => $this->sourceType->value,
            'source_label' => $this->sourceLabel,
            'methodology' => $this->methodology,
            'benchmark_version' => $this->benchmarkVersion,
        ];
    }
}
