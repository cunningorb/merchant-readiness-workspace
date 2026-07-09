<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\Recommendation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recommendation>
 */
class RecommendationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'category' => fake()->randomElement([
                'business', 'catalog', 'policy', 'exchanges', 'operations', 'platform',
            ]),
            'priority' => fake()->randomElement(['high', 'medium', 'low']),
            'expected_impact' => fake()->sentence(6),
        ];
    }
}
