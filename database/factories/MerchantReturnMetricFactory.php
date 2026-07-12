<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\Merchant;
use App\Models\MerchantReturnMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchantReturnMetric>
 */
class MerchantReturnMetricFactory extends Factory
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
            'refund_amount_total' => fake()->randomFloat(2, 1000, 50000),
            'refund_units_total' => fake()->numberBetween(10, 1000),
            'estimated_refund_rate' => fake()->randomFloat(4, 0.01, 0.3),
            'exchange_share' => fake()->randomFloat(4, 0, 0.5),
            'refund_only_share' => fake()->randomFloat(4, 0.5, 1),
            'average_time_to_refund_days' => fake()->randomFloat(2, 1, 14),
            'top_refund_categories' => ['sizing', 'damaged'],
        ];
    }
}
