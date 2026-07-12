<?php

namespace Database\Factories;

use App\Models\BenchmarkSet;
use App\Models\BenchmarkValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BenchmarkValue>
 */
class BenchmarkValueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'benchmark_set_id' => BenchmarkSet::factory(),
            'metric_key' => fake()->randomElement([
                'return_rate',
                'exchange_rate',
                'average_processing_time',
                'manual_review_rate',
            ]),
            'industry' => fake()->randomElement(['apparel', 'footwear', 'electronics', 'home_goods']),
            'platform' => fake()->randomElement(['shopify', 'woocommerce', 'bigcommerce', 'magento']),
            'annual_order_volume_min' => fake()->randomFloat(2, 0, 5000),
            'annual_order_volume_max' => fake()->randomFloat(2, 5000, 50000),
            'catalog_profile' => fake()->randomElement(['small', 'medium', 'large']),
            'minimum_value' => fake()->randomFloat(2, 5, 15),
            'maximum_value' => fake()->randomFloat(2, 15, 30),
            'unit' => 'percent',
            'sample_size' => fake()->numberBetween(50, 500),
            'notes' => fake()->sentence(),
        ];
    }
}
