<?php

namespace Tests\Unit\Models;

use App\Models\MerchantProduct;
use App\Models\MerchantProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantProductVariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_variant_belongs_to_product(): void
    {
        $product = MerchantProduct::factory()->create();
        $variant = MerchantProductVariant::factory()->for($product)->create();

        $this->assertTrue($variant->merchantProduct->is($product));
    }

    public function test_deleting_product_cascades_to_variants(): void
    {
        $product = MerchantProduct::factory()->create();
        $variant = MerchantProductVariant::factory()->for($product)->create();

        $product->delete();

        $this->assertDatabaseMissing('merchant_product_variants', ['id' => $variant->id]);
    }

    public function test_option_names_and_values_cast_to_array(): void
    {
        $variant = MerchantProductVariant::factory()->create([
            'option_names' => ['Size', 'Color'],
            'option_values' => ['Large', 'Red'],
        ]);

        $fresh = $variant->fresh();

        $this->assertSame(['Size', 'Color'], $fresh->option_names);
        $this->assertSame(['Large', 'Red'], $fresh->option_values);
    }

    public function test_price_casts_to_decimal_string(): void
    {
        $variant = MerchantProductVariant::factory()->create(['price' => 12.5]);

        $this->assertSame('12.50', $variant->fresh()->price);
    }

    public function test_inventory_tracked_casts_to_boolean(): void
    {
        $variant = MerchantProductVariant::factory()->create(['inventory_tracked' => 1]);

        $this->assertTrue($variant->fresh()->inventory_tracked);
    }

    public function test_factory_creates_valid_variant(): void
    {
        $variant = MerchantProductVariant::factory()->create();

        $this->assertDatabaseHas('merchant_product_variants', ['id' => $variant->id]);
    }
}
