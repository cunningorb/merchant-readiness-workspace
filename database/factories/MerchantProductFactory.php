<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\MerchantProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchantProduct>
 */
class MerchantProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'source_provider' => 'demo',
            'source_import_id' => null,
            'provider_product_id' => fake()->uuid(),
            'title' => fake()->words(3, true),
            'product_type' => fake()->randomElement(['apparel', 'footwear', 'accessories']),
            'vendor' => fake()->company(),
            'tags' => [fake()->word(), fake()->word()],
            'description_length' => fake()->numberBetween(0, 500),
            'status' => 'active',
            'variant_count' => fake()->numberBetween(1, 10),
            'has_size_option' => fake()->boolean(),
            'has_color_option' => fake()->boolean(),
            'sparse_description' => false,
            'media_count' => fake()->numberBetween(0, 8),
            'low_media' => false,
            'price' => fake()->randomFloat(2, 10, 200),
            'compare_at_price' => fake()->randomFloat(2, 200, 300),
            'sku' => fake()->bothify('SKU-####'),
            'inventory_tracked' => true,
        ];
    }
}
