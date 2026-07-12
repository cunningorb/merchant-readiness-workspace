<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\Merchant;
use App\Models\MerchantOrderMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchantOrderMetric>
 */
class MerchantOrderMetricFactory extends Factory
{
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'assessment_id' => Assessment::factory(),
            'source_provider' => 'demo',
            'source_import_id' => null,
            'source_period_start' => now()->subYear()->toDateString(),
            'source_period_end' => now()->toDateString(),
            'order_count' => fake()->numberBetween(100, 5000),
            'annualized_order_volume' => fake()->numberBetween(1000, 60000),
            'average_order_value' => fake()->randomFloat(2, 30, 250),
        ];
    }
}
