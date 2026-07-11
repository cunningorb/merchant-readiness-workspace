<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\AssessmentOpportunity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentOpportunity>
 */
class AssessmentOpportunityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'type' => fake()->randomElement(['retained_revenue', 'time_savings']),
            'title' => fake()->sentence(4),
            'summary' => fake()->paragraph(),
            'minimum_value' => fake()->randomFloat(2, 1000, 5000),
            'maximum_value' => fake()->randomFloat(2, 5000, 20000),
            'unit' => 'usd_per_year',
            'confidence' => fake()->randomElement(['high', 'medium', 'low']),
            'effort' => fake()->randomElement(['low', 'medium', 'high']),
            'assumptions' => ['average_order_value' => 75],
            'evidence' => [
                'inputs' => [],
                'source_answer_keys' => [],
                'why' => [],
            ],
            'formula_version' => 'v1',
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
