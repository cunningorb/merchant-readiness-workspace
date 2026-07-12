<?php

namespace Tests\Unit\Services\Imports\Csv;

use App\Enums\ImportMethod;
use App\Models\Assessment;
use App\Models\DataImport;
use App\Models\DataImportError;
use App\Models\DataImportFile;
use App\Models\Merchant;
use App\Models\MerchantProduct;
use App\Services\Imports\Csv\CsvCatalogImporter;
use App\Services\Imports\ImportCoordinator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CsvCatalogImporterTest extends TestCase
{
    use RefreshDatabase;

    private function importWithFile(string $csv): array
    {
        Storage::fake('local');

        $assessment = Assessment::factory()->create([
            'merchant_id' => Merchant::factory()->create()->id,
        ]);

        $dataImport = DataImport::factory()->for($assessment)->create([
            'provider' => 'csv',
            'method' => ImportMethod::Csv->value,
        ]);

        $path = "imports/{$dataImport->id}/catalog-products.csv";
        Storage::disk('local')->put($path, $csv);

        $file = DataImportFile::factory()->for($dataImport)->create([
            'data_type' => ImportCoordinator::DATA_TYPE_CATALOG,
            'stored_path' => $path,
            'original_filename' => 'products.csv',
            'row_count' => null,
            'accepted_count' => null,
            'rejected_count' => null,
            'fingerprint' => hash('sha256', $csv),
        ]);

        return [$dataImport, $file];
    }

    public function test_well_formed_file_parses_every_row_correctly(): void
    {
        $csv = <<<'CSV'
        title,product_type,vendor,tags,description_length,status,variant_count,has_size_option,has_color_option,media_count,price,compare_at_price,sku,inventory_tracked
        Classic Tee,Apparel,Acme,"sale;new",120,active,4,true,true,5,19.99,24.99,TEE-001,true
        Simple Vase,Home goods,Acme,,80,active,1,false,false,6,29.5,,VASE-001,0
        CSV;

        [$dataImport, $file] = $this->importWithFile($csv);

        (new CsvCatalogImporter)->importCatalog($dataImport);

        $products = MerchantProduct::query()->orderBy('id')->get();
        $this->assertCount(2, $products);

        $tee = $products->firstWhere('title', 'Classic Tee');
        $this->assertSame('csv', $tee->source_provider);
        $this->assertSame($dataImport->id, $tee->source_import_id);
        $this->assertSame($dataImport->assessment->merchant_id, $tee->merchant_id);
        $this->assertSame('Apparel', $tee->product_type);
        $this->assertSame('Acme', $tee->vendor);
        $this->assertSame(['sale', 'new'], $tee->tags);
        $this->assertSame(120, $tee->description_length);
        $this->assertSame('active', $tee->status);
        $this->assertSame(4, $tee->variant_count);
        $this->assertTrue($tee->has_size_option);
        $this->assertTrue($tee->has_color_option);
        $this->assertSame(5, $tee->media_count);
        $this->assertSame('19.99', $tee->price);
        $this->assertSame('24.99', $tee->compare_at_price);
        $this->assertSame('TEE-001', $tee->sku);
        $this->assertSame('TEE-001', $tee->provider_product_id);
        $this->assertTrue($tee->inventory_tracked);

        $vase = $products->firstWhere('title', 'Simple Vase');
        $this->assertNull($vase->tags);
        $this->assertFalse($vase->has_size_option);
        $this->assertFalse($vase->has_color_option);
        $this->assertNull($vase->compare_at_price);
        $this->assertFalse($vase->inventory_tracked);

        $this->assertSame(2, $file->fresh()->row_count);
        $this->assertSame(2, $file->fresh()->accepted_count);
        $this->assertSame(0, $file->fresh()->rejected_count);
    }

    public function test_a_row_missing_title_is_generated_a_fallback_provider_product_id_when_sku_is_also_blank(): void
    {
        $csv = <<<'CSV'
        title,product_type,vendor,tags,description_length,status,variant_count,has_size_option,has_color_option,media_count,price,compare_at_price,sku,inventory_tracked
        No Sku Product,Apparel,Acme,,10,active,1,false,false,1,5.00,,,true
        CSV;

        [$dataImport] = $this->importWithFile($csv);

        (new CsvCatalogImporter)->importCatalog($dataImport);

        $product = MerchantProduct::query()->firstOrFail();
        $this->assertNull($product->sku);
        $this->assertSame("csv-{$dataImport->id}-row-1", $product->provider_product_id);
    }

    public function test_partial_failure_imports_valid_rows_and_rejects_invalid_ones_without_failing_the_file(): void
    {
        $csv = <<<'CSV'
        title,product_type,vendor,tags,description_length,status,variant_count,has_size_option,has_color_option,media_count,price,compare_at_price,sku,inventory_tracked
        Good Product,Apparel,Acme,sale,100,active,2,true,false,3,10.00,,SKU-1,true
        ,Apparel,Acme,sale,100,active,2,true,false,3,10.00,,SKU-2,true
        Bad Numeric,Apparel,Acme,sale,abc,active,2,true,false,3,10.00,,SKU-3,true
        Another Good,Apparel,Acme,sale,100,active,2,true,false,3,10.00,,SKU-4,true
        CSV;

        [$dataImport, $file] = $this->importWithFile($csv);

        (new CsvCatalogImporter)->importCatalog($dataImport);

        // The literal acceptance criterion: a partially-bad products file
        // still yields SOME imported products, not zero.
        $products = MerchantProduct::query()->orderBy('id')->get();
        $this->assertCount(2, $products);
        $this->assertSame(['Good Product', 'Another Good'], $products->pluck('title')->all());

        $file = $file->fresh();
        $this->assertSame(4, $file->row_count);
        $this->assertSame(2, $file->accepted_count);
        $this->assertSame(2, $file->rejected_count);

        $errors = DataImportError::query()->where('data_import_id', $dataImport->id)->orderBy('row_number')->get();
        $this->assertCount(2, $errors);
        $this->assertSame(2, $errors[0]->row_number);
        $this->assertStringContainsString('title', $errors[0]->message);
        $this->assertSame(3, $errors[1]->row_number);
        $this->assertStringContainsString('numeric', $errors[1]->message);
        $this->assertSame($file->id, $errors[0]->data_import_file_id);
    }
}
