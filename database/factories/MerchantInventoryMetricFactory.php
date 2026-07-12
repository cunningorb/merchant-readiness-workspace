<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\Merchant;
use App\Models\MerchantInventoryMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchantInventoryMetric>
 */
class MerchantInventoryMetricFactory extends Factory
{
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'assessment_id' => Assessment::factory(),
            'source_provider' => 'demo',
            'source_import_id' => null,
            'percent_multi_location_stock' => fake()->randomFloat(2, 0, 100),
            'percent_low_or_zero_stock' => fake()->randomFloat(2, 0, 100),
            'sku_completeness' => fake()->randomFloat(2, 0, 100),
            'exchange_availability_risk' => fake()->randomElement(['low', 'medium', 'high']),
        ];
    }
}
