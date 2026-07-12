<?php

namespace Database\Factories;

use App\Models\BenchmarkSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BenchmarkSet>
 */
class BenchmarkSetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' benchmark',
            'version' => '2026.1',
            'source_type' => fake()->randomElement([
                'illustrative',
                'configured',
                'external_reference',
                'proprietary',
            ]),
            'source_label' => fake()->company().' dataset',
            'methodology' => fake()->paragraph(),
            'effective_from' => now()->subMonths(3),
            'effective_to' => null,
            'is_active' => true,
        ];
    }
}
