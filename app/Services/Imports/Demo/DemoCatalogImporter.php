<?php

namespace App\Services\Imports\Demo;

use App\Contracts\CatalogImporter;
use App\Models\DataImport;
use App\Models\MerchantProduct;
use App\Models\MerchantProductVariant;

/**
 * Writes the fixture products (and a representative sample of variants) for
 * a demo scenario. MerchantProductVariant rows carry no source_provider /
 * source_import_id of their own — the schema scopes that lineage to the
 * parent MerchantProduct via merchant_product_id, so a variant's provenance
 * is always read through its product.
 */
class DemoCatalogImporter implements CatalogImporter
{
    use ResolvesDemoScenario;

    public function importCatalog(DataImport $dataImport): void
    {
        $scenario = $this->scenarioFor($dataImport);
        $data = DemoScenarios::for($scenario);
        $merchantId = $dataImport->assessment->merchant_id;

        foreach ($data['products'] as $index => $productData) {
            $product = MerchantProduct::create([
                'merchant_id' => $merchantId,
                'source_provider' => 'demo',
                'source_import_id' => $dataImport->id,
                'provider_product_id' => "demo-{$scenario}-product-".($index + 1),
                'title' => $productData['title'],
                'product_type' => $productData['product_type'],
                'variant_count' => $productData['variant_count'],
                'has_size_option' => $productData['has_size_option'],
                'has_color_option' => $productData['has_color_option'],
                'sparse_description' => $productData['sparse_description'],
                'media_count' => $productData['media_count'],
                'low_media' => $productData['low_media'],
                'price' => $productData['price'],
                'sku' => 'DEMO-'.strtoupper(substr($scenario, 0, 3)).'-'.($index + 1),
                'inventory_tracked' => true,
            ]);

            foreach ($productData['variants'] as $variantIndex => $variant) {
                MerchantProductVariant::create([
                    'merchant_product_id' => $product->id,
                    'provider_variant_id' => "demo-{$scenario}-product-".($index + 1).'-variant-'.($variantIndex + 1),
                    'option_names' => $variant['option_names'],
                    'option_values' => $variant['option_values'],
                    'price' => $productData['price'],
                    'sku' => $product->sku.'-'.($variantIndex + 1),
                    'inventory_tracked' => true,
                ]);
            }
        }
    }
}
