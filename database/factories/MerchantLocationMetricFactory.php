<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\Merchant;
use App\Models\MerchantLocationMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchantLocationMetric>
 */
class MerchantLocationMetricFactory extends Factory
{
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'assessment_id' => Assessment::factory(),
            'source_provider' => 'demo',
            'source_import_id' => null,
            'active_location_count' => fake()->numberBetween(1, 20),
            'operational_complexity_score' => fake()->randomElement(['low', 'medium', 'high']),
        ];
    }
}
