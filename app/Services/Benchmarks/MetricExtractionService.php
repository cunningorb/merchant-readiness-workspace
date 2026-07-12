<?php

namespace App\Services\Benchmarks;

use App\Models\Assessment;

/**
 * Derives the three honestly-computable benchmark metrics and comparison
 * context (industry, platform, annual order volume) from an assessment's
 * banded questionnaire answers. Never fabricates a value when the underlying
 * answer is missing or the band string isn't recognized.
 */
class MetricExtractionService
{
    /**
     * @return array<string, ?float>
     */
    public function extract(Assessment $assessment): array
    {
        $metrics = [];

        foreach (config('benchmarks.metrics') as $metricKey => $metricConfig) {
            $metrics[$metricKey] = $this->extractMetric($assessment, $metricKey, $metricConfig);
        }

        return $metrics;
    }

    /**
     * @return array{industry: ?string, platform: ?string, annual_order_volume: ?float}
     */
    public function deriveContext(Assessment $assessment): array
    {
        $industry = $this->deriveIndustry($assessment);

        return [
            'industry' => $industry,
            'platform' => $this->stringAnswer($assessment, 'platform.ecommerce_platform'),
            'annual_order_volume' => $this->deriveAnnualOrderVolume($assessment),
        ];
    }

    /**
     * @param  array<string, mixed>  $metricConfig
     */
    private function extractMetric(Assessment $assessment, string $metricKey, array $metricConfig): ?float
    {
        $band = $assessment->answerValue($metricConfig['answer_key']);

        $bandValues = $metricConfig['band_values'] ?? $this->sharedBandValuesFor($metricKey);

        return $this->bandValue($bandValues, $band);
    }

    /**
     * A handful of band maps (order volume, weekly manual hours) already live in
     * config/assessment.php for the opportunity engine; reuse them instead of
     * duplicating the numbers here.
     *
     * @return array<string, mixed>
     */
    private function sharedBandValuesFor(string $metricKey): array
    {
        return match ($metricKey) {
            'manual_processing_hours_per_week' => config('assessment.opportunities.weekly_manual_hours_band_midpoints'),
            default => [],
        };
    }

    private function deriveIndustry(Assessment $assessment): ?string
    {
        $selectedCategories = $assessment->answerValue('catalog.fit_sensitive_categories');

        if (! is_array($selectedCategories) || $selectedCategories === []) {
            return null;
        }

        foreach (config('benchmarks.catalog_profile_priority') as $category => $segment) {
            if (in_array($category, $selectedCategories, true)) {
                return $segment;
            }
        }

        return null;
    }

    private function deriveAnnualOrderVolume(Assessment $assessment): ?float
    {
        $band = $assessment->answerValue('business.monthly_order_volume');
        $monthlyMidpoint = $this->bandValue(config('assessment.opportunities.order_volume_band_midpoints'), $band);

        if ($monthlyMidpoint === null) {
            return null;
        }

        return $monthlyMidpoint * 12;
    }

    private function stringAnswer(Assessment $assessment, string $questionKey): ?string
    {
        $value = $assessment->answerValue($questionKey);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $map
     */
    private function bandValue(array $map, mixed $band): ?float
    {
        if (! is_string($band) || ! array_key_exists($band, $map)) {
            return null;
        }

        return (float) $map[$band];
    }
}
