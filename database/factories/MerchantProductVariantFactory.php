<?php

namespace Database\Factories;

use App\Models\MerchantProduct;
use App\Models\MerchantProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchantProductVariant>
 */
class MerchantProductVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'merchant_product_id' => MerchantProduct::factory(),
            'provider_variant_id' => fake()->uuid(),
            'option_names' => ['Size', 'Color'],
            'option_values' => ['Medium', 'Blue'],
            'price' => fake()->randomFloat(2, 10, 200),
            'sku' => fake()->bothify('SKU-####-??'),
            'inventory_tracked' => true,
        ];
    }
}
