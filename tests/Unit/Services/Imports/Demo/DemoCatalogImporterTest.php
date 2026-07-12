<?php

namespace Tests\Unit\Services\Imports\Demo;

use App\Enums\ImportMethod;
use App\Enums\ImportStatus;
use App\Models\Assessment;
use App\Models\DataImport;
use App\Models\Merchant;
use App\Models\MerchantProduct;
use App\Services\Imports\Demo\DemoCatalogImporter;
use App\Services\Imports\Demo\DemoScenarios;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoCatalogImporterTest extends TestCase
{
    use RefreshDatabase;

    private function importFor(string $scenario): DataImport
    {
        $assessment = Assessment::factory()->create([
            'merchant_id' => Merchant::factory()->create()->id,
        ]);

        return DataImport::factory()->for($assessment)->create([
            'provider' => 'demo',
            'method' => ImportMethod::Demo->value,
            'status' => ImportStatus::Created->value,
            'metadata' => ['demo_scenario' => $scenario],
        ]);
    }

    public function test_apparel_scenario_creates_six_products_with_correct_flags_and_stamps(): void
    {
        $dataImport = $this->importFor(DemoScenarios::APPAREL);

        (new DemoCatalogImporter)->importCatalog($dataImport);

        $products = MerchantProduct::query()->orderBy('id')->get();
        $this->assertCount(6, $products);

        foreach ($products as $product) {
            $this->assertSame('demo', $product->source_provider);
            $this->assertSame($dataImport->id, $product->source_import_id);
            $this->assertSame($dataImport->assessment->merchant_id, $product->merchant_id);
            $this->assertSame('Apparel', $product->product_type);
        }

        $sizeAndColor = $products->filter(fn ($p) => $p->has_size_option && $p->has_color_option);
        $this->assertCount(4, $sizeAndColor);
        foreach ($sizeAndColor as $product) {
            $this->assertGreaterThanOrEqual(4, $product->variant_count);
            $this->assertLessThanOrEqual(12, $product->variant_count);
        }

        $colorOnly = $products->filter(fn ($p) => ! $p->has_size_option && $p->has_color_option);
        $this->assertCount(2, $colorOnly);
        foreach ($colorOnly as $product) {
            $this->assertGreaterThanOrEqual(2, $product->variant_count);
            $this->assertLessThanOrEqual(4, $product->variant_count);
        }

        $sparse = $products->filter(fn ($p) => $p->sparse_description);
        $this->assertCount(2, $sparse);
        foreach ($sparse as $product) {
            $this->assertSame(1, $product->media_count);
            $this->assertTrue($product->low_media);
        }

        $notSparse = $products->filter(fn ($p) => ! $p->sparse_description);
        $this->assertCount(4, $notSparse);
        foreach ($notSparse as $product) {
            $this->assertGreaterThanOrEqual(4, $product->media_count);
            $this->assertLessThanOrEqual(6, $product->media_count);
        }

        $leggings = $products->firstWhere('title', 'Performance Leggings');
        $this->assertNotNull($leggings);
        $this->assertTrue($leggings->has_size_option);
        $this->assertTrue($leggings->has_color_option);
        $this->assertTrue($leggings->sparse_description);
    }

    public function test_footwear_scenario_creates_five_products_with_correct_flags(): void
    {
        $dataImport = $this->importFor(DemoScenarios::FOOTWEAR);

        (new DemoCatalogImporter)->importCatalog($dataImport);

        $products = MerchantProduct::query()->orderBy('id')->get();
        $this->assertCount(5, $products);

        foreach ($products as $product) {
            $this->assertTrue($product->has_size_option);
            $this->assertSame('Footwear', $product->product_type);
            $this->assertGreaterThanOrEqual(6, $product->variant_count);
            $this->assertLessThanOrEqual(14, $product->variant_count);
            $this->assertSame('demo', $product->source_provider);
            $this->assertSame($dataImport->id, $product->source_import_id);
        }

        $colorOption = $products->filter(fn ($p) => $p->has_color_option);
        $this->assertCount(2, $colorOption);

        $sparse = $products->filter(fn ($p) => $p->sparse_description);
        $this->assertCount(1, $sparse);
        $this->assertTrue($sparse->first()->low_media);
        $this->assertSame(1, $sparse->first()->media_count);

        $boot = $products->firstWhere('title', 'All-Terrain Boot');
        $this->assertNotNull($boot);
        $this->assertTrue($boot->sparse_description);
    }

    public function test_home_goods_scenario_creates_four_products_with_no_options(): void
    {
        $dataImport = $this->importFor(DemoScenarios::HOME_GOODS);

        (new DemoCatalogImporter)->importCatalog($dataImport);

        $products = MerchantProduct::query()->orderBy('id')->get();
        $this->assertCount(4, $products);

        foreach ($products as $product) {
            $this->assertFalse($product->has_size_option);
            $this->assertFalse($product->has_color_option);
            $this->assertFalse($product->sparse_description);
            $this->assertFalse($product->low_media);
            $this->assertSame('Home goods', $product->product_type);
            $this->assertGreaterThanOrEqual(1, $product->variant_count);
            $this->assertLessThanOrEqual(3, $product->variant_count);
            $this->assertGreaterThanOrEqual(5, $product->media_count);
            $this->assertLessThanOrEqual(8, $product->media_count);
        }
    }

    public function test_variants_are_created_consistent_with_product_options(): void
    {
        $dataImport = $this->importFor(DemoScenarios::APPAREL);

        (new DemoCatalogImporter)->importCatalog($dataImport);

        $tee = MerchantProduct::query()->where('title', 'Classic Crew Tee')->firstOrFail();
        $variants = $tee->variants()->get();

        $this->assertGreaterThanOrEqual(2, $variants->count());
        $this->assertLessThanOrEqual(4, $variants->count());

        foreach ($variants as $variant) {
            $this->assertSame(['Size', 'Color'], $variant->option_names);
            $this->assertCount(2, $variant->option_values);
        }

        $hoodie = MerchantProduct::query()->where('title', 'Oversized Hoodie')->firstOrFail();
        foreach ($hoodie->variants()->get() as $variant) {
            $this->assertSame(['Color'], $variant->option_names);
        }
    }

    public function test_home_goods_products_get_a_single_default_variant(): void
    {
        $dataImport = $this->importFor(DemoScenarios::HOME_GOODS);

        (new DemoCatalogImporter)->importCatalog($dataImport);

        $vase = MerchantProduct::query()->where('title', 'Ceramic Vase Set')->firstOrFail();
        $variants = $vase->variants()->get();

        $this->assertCount(1, $variants);
        $this->assertNull($variants->first()->option_names);
        $this->assertNull($variants->first()->option_values);
    }
}
