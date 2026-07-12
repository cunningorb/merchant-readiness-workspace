<?php

namespace App\Services\Benchmarks;

use App\Enums\BenchmarkSourceType;
use App\Models\Assessment;
use App\Models\BenchmarkValue;

/**
 * Compares a submitted assessment's honestly-computable metrics against
 * resolved peer benchmark ranges. Mirrors the no-fabrication discipline of
 * OpportunityCalculationService: a metric with a missing or unrecognized
 * answer, or one with no matching benchmark, is skipped entirely rather
 * than guessed. Interpretation text is neutral and descriptive, never
 * evaluative or causal.
 */
class BenchmarkComparisonService
{
    /**
     * Fixed metric order; also the natural sort_order (0/1/2) for whichever
     * metrics end up with a comparison.
     */
    private const METRIC_ORDER = [
        'return_window_days',
        'manual_processing_hours_per_week',
        'catalog_sku_count',
    ];

    private const UNIT_LABELS = [
        'days' => 'days',
        'hours_per_week' => 'hrs/week',
        'sku_count' => 'SKUs',
    ];

    public function __construct(
        private readonly MetricExtractionService $extraction,
        private readonly BenchmarkResolver $resolver,
    ) {}

    /**
     * @return array<int, BenchmarkComparisonResult>
     */
    public function compare(Assessment $assessment): array
    {
        $assessment->loadMissing('answers');

        $context = $this->extraction->deriveContext($assessment);
        $metrics = $this->extraction->extract($assessment);

        $results = [];

        foreach (self::METRIC_ORDER as $metricKey) {
            $merchantValue = $metrics[$metricKey] ?? null;

            if ($merchantValue === null) {
                continue;
            }

            $benchmarkValue = $this->resolver->resolve($metricKey, $context);

            // A benchmark row with no usable range is not a usable benchmark:
            // there is nothing to compare against, so skip rather than guess.
            if ($benchmarkValue === null || $benchmarkValue->minimum_value === null || $benchmarkValue->maximum_value === null) {
                continue;
            }

            $results[] = $this->buildResult($metricKey, $merchantValue, $benchmarkValue);
        }

        return $results;
    }

    private function buildResult(string $metricKey, float $merchantValue, BenchmarkValue $benchmarkValue): BenchmarkComparisonResult
    {
        // Callers only reach here once minimum_value/maximum_value have been
        // confirmed non-null (see compare()).
        $range = new ComparisonRange(
            minimum: (float) $benchmarkValue->minimum_value,
            maximum: (float) $benchmarkValue->maximum_value,
            unit: $benchmarkValue->unit,
        );

        $benchmarkSet = $benchmarkValue->benchmarkSet;

        return new BenchmarkComparisonResult(
            metricKey: $metricKey,
            label: config("benchmarks.metrics.{$metricKey}.label"),
            merchantValue: $merchantValue,
            range: $range,
            interpretation: $this->interpretation($merchantValue, $range, $benchmarkSet->source_label),
            sourceType: BenchmarkSourceType::from($benchmarkSet->source_type),
            sourceLabel: $benchmarkSet->source_label,
            methodology: $benchmarkSet->methodology,
            benchmarkVersion: $benchmarkSet->version,
            benchmarkSetId: $benchmarkSet->id,
        );
    }

    /**
     * Neutral, comparative, non-causal: states whether the merchant's value
     * falls below, within, or above the resolved reference range. Never
     * implies the merchant is doing better or worse than peers, and never
     * implies a guaranteed or proven outcome. Only called with a fully
     * bounded range (see compare()).
     */
    private function interpretation(float $merchantValue, ComparisonRange $range, string $sourceLabel): string
    {
        $unitLabel = self::UNIT_LABELS[$range->unit] ?? $range->unit;
        $merchantFormatted = $this->formatNumber($merchantValue);

        $position = match (true) {
            $merchantValue < $range->minimum => 'below',
            $merchantValue > $range->maximum => 'above',
            default => 'within',
        };

        $rangeFormatted = $this->formatNumber($range->minimum).'–'.$this->formatNumber($range->maximum);

        return "{$merchantFormatted} {$unitLabel} is {$position} the {$sourceLabel} range of {$rangeFormatted} {$unitLabel}.";
    }

    private function formatNumber(float $value): string
    {
        return number_format($value);
    }
}
