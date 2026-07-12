<?php

namespace Tests\Unit\Models;

use App\Models\DataImport;
use App\Models\Merchant;
use App\Models\MerchantProduct;
use App\Models\MerchantProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_product_belongs_to_merchant(): void
    {
        $merchant = Merchant::factory()->create();
        $product = MerchantProduct::factory()->for($merchant)->create();

        $this->assertTrue($product->merchant->is($merchant));
    }

    public function test_merchant_product_has_many_variants(): void
    {
        $product = MerchantProduct::factory()->create();
        $variant = MerchantProductVariant::factory()->for($product)->create();

        $this->assertTrue($product->fresh()->variants->contains($variant));
    }

    public function test_deleting_source_import_nullifies_merchant_product_foreign_key(): void
    {
        $import = DataImport::factory()->create();
        $product = MerchantProduct::factory()->create(['source_import_id' => $import->id]);

        $import->delete();

        $this->assertNull($product->fresh()->source_import_id);
        $this->assertDatabaseHas('merchant_products', ['id' => $product->id]);
    }

    public function test_deleting_merchant_cascades_to_products(): void
    {
        $merchant = Merchant::factory()->create();
        $product = MerchantProduct::factory()->for($merchant)->create();

        $merchant->delete();

        $this->assertDatabaseMissing('merchant_products', ['id' => $product->id]);
    }

    public function test_boolean_columns_cast_to_boolean(): void
    {
        $product = MerchantProduct::factory()->create([
            'has_size_option' => 1,
            'has_color_option' => 0,
            'sparse_description' => 1,
            'low_media' => 0,
            'inventory_tracked' => 1,
        ]);

        $fresh = $product->fresh();

        $this->assertIsBool($fresh->has_size_option);
        $this->assertTrue($fresh->has_size_option);
        $this->assertIsBool($fresh->has_color_option);
        $this->assertFalse($fresh->has_color_option);
    }

    public function test_tags_casts_to_array(): void
    {
        $product = MerchantProduct::factory()->create(['tags' => ['returns-friendly', 'seasonal']]);

        $this->assertSame(['returns-friendly', 'seasonal'], $product->fresh()->tags);
    }

    public function test_price_columns_cast_to_decimal_strings(): void
    {
        $product = MerchantProduct::factory()->create([
            'price' => 19.5,
            'compare_at_price' => 25,
        ]);

        $fresh = $product->fresh();

        $this->assertSame('19.50', $fresh->price);
        $this->assertSame('25.00', $fresh->compare_at_price);
    }

    public function test_factory_creates_valid_merchant_product(): void
    {
        $product = MerchantProduct::factory()->create();

        $this->assertDatabaseHas('merchant_products', ['id' => $product->id]);
    }
}
