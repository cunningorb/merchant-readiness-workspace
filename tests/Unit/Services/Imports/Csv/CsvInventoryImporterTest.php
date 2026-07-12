<?php

namespace Tests\Unit\Services\Imports\Csv;

use App\Enums\ImportMethod;
use App\Models\Assessment;
use App\Models\DataImport;
use App\Models\DataImportFile;
use App\Models\Merchant;
use App\Models\MerchantInventoryMetric;
use App\Models\MerchantLocationMetric;
use App\Services\Imports\Csv\CsvInventoryImporter;
use App\Services\Imports\ImportCoordinator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class CsvInventoryImporterTest extends TestCase
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

        $path = "imports/{$dataImport->id}/inventory_locations-inventory_and_locations.csv";
        Storage::disk('local')->put($path, $csv);

        $file = DataImportFile::factory()->for($dataImport)->create([
            'data_type' => ImportCoordinator::DATA_TYPE_INVENTORY_LOCATIONS,
            'stored_path' => $path,
            'original_filename' => 'inventory_and_locations.csv',
            'row_count' => null,
            'accepted_count' => null,
            'rejected_count' => null,
            'fingerprint' => hash('sha256', $csv),
        ]);

        return [$dataImport, $file];
    }

    public function test_well_formed_row_parses_into_inventory_and_location_metrics(): void
    {
        $csv = <<<'CSV'
        percent_multi_location_stock,percent_low_or_zero_stock,sku_completeness,exchange_availability_risk,active_location_count,operational_complexity_score
        45.5,12.0,92.5,medium,3,medium
        CSV;

        [$dataImport, $file] = $this->importWithFile($csv);

        (new CsvInventoryImporter)->importInventory($dataImport);

        $inventory = MerchantInventoryMetric::query()->where('source_import_id', $dataImport->id)->firstOrFail();
        $this->assertSame('csv', $inventory->source_provider);
        $this->assertSame($dataImport->assessment->merchant_id, $inventory->merchant_id);
        $this->assertSame($dataImport->assessment_id, $inventory->assessment_id);
        $this->assertSame('45.50', $inventory->percent_multi_location_stock);
        $this->assertSame('12.00', $inventory->percent_low_or_zero_stock);
        $this->assertSame('92.50', $inventory->sku_completeness);
        $this->assertSame('medium', $inventory->exchange_availability_risk);

        $location = MerchantLocationMetric::query()->where('source_import_id', $dataImport->id)->firstOrFail();
        $this->assertSame('csv', $location->source_provider);
        $this->assertSame(3, $location->active_location_count);
        $this->assertSame('medium', $location->operational_complexity_score);

        $file = $file->fresh();
        $this->assertSame(1, $file->row_count);
        $this->assertSame(1, $file->accepted_count);
        $this->assertSame(0, $file->rejected_count);
    }

    public function test_entirely_blank_row_rejects_the_whole_file(): void
    {
        $csv = <<<'CSV'
        percent_multi_location_stock,percent_low_or_zero_stock,sku_completeness,exchange_availability_risk,active_location_count,operational_complexity_score
        ,,,,,
        CSV;

        [$dataImport, $file] = $this->importWithFile($csv);

        $this->expectException(RuntimeException::class);

        try {
            (new CsvInventoryImporter)->importInventory($dataImport);
        } finally {
            $this->assertSame(0, MerchantInventoryMetric::query()->where('source_import_id', $dataImport->id)->count());
            $this->assertSame(0, MerchantLocationMetric::query()->where('source_import_id', $dataImport->id)->count());
            $this->assertSame(1, $file->fresh()->rejected_count);
        }
    }

    public function test_partial_data_with_some_blank_fields_is_still_accepted(): void
    {
        $csv = <<<'CSV'
        percent_multi_location_stock,percent_low_or_zero_stock,sku_completeness,exchange_availability_risk,active_location_count,operational_complexity_score
        ,,,,4,
        CSV;

        [$dataImport] = $this->importWithFile($csv);

        (new CsvInventoryImporter)->importInventory($dataImport);

        $location = MerchantLocationMetric::query()->where('source_import_id', $dataImport->id)->firstOrFail();
        $this->assertSame(4, $location->active_location_count);
        $this->assertNull($location->operational_complexity_score);

        $inventory = MerchantInventoryMetric::query()->where('source_import_id', $dataImport->id)->firstOrFail();
        $this->assertNull($inventory->percent_multi_location_stock);
    }
}
