<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\AssessmentBenchmarkComparison;
use App\Models\BenchmarkSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentBenchmarkComparison>
 */
class AssessmentBenchmarkComparisonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'benchmark_set_id' => BenchmarkSet::factory(),
            'metric_key' => fake()->randomElement([
                'return_rate',
                'exchange_rate',
                'average_processing_time',
                'manual_review_rate',
            ]),
            'label' => fake()->sentence(3),
            'merchant_value' => fake()->randomFloat(2, 0, 40),
            'minimum_value' => fake()->randomFloat(2, 5, 15),
            'maximum_value' => fake()->randomFloat(2, 15, 30),
            'unit' => 'percent',
            'interpretation' => fake()->paragraph(),
            'source_type' => fake()->randomElement([
                'illustrative',
                'configured',
                'external_reference',
                'proprietary',
            ]),
            'source_label' => fake()->company().' dataset',
            'methodology' => fake()->paragraph(),
            'benchmark_version' => '2026.1',
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
