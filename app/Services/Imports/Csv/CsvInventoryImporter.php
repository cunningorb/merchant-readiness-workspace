<?php

namespace App\Services\Imports\Csv;

use App\Contracts\InventoryImporter;
use App\Models\DataImport;
use App\Models\MerchantInventoryMetric;
use App\Models\MerchantLocationMetric;
use App\Services\Imports\Csv\Concerns\LocatesDataImportFile;
use App\Services\Imports\Csv\Concerns\ParsesCsvValues;
use App\Services\Imports\Csv\Concerns\ReadsCsvRows;
use App\Services\Imports\ImportCoordinator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Parses inventory_and_locations.csv — the same Milestone 11 simplification
 * as orders_and_returns.csv: ONE pre-aggregated summary row rather than raw
 * per-SKU/per-location data, for the same reason (real aggregation logic is
 * Milestone 12's job).
 *
 * No single field is strictly required, but an entirely empty or
 * unparseable row rejects the whole file — there's nothing useful to import
 * from a summary row where every column is blank or unparseable.
 */
class CsvInventoryImporter implements InventoryImporter
{
    use LocatesDataImportFile, ParsesCsvValues, ReadsCsvRows;

    public function importInventory(DataImport $dataImport): void
    {
        $file = $this->fileFor($dataImport, ImportCoordinator::DATA_TYPE_INVENTORY_LOCATIONS);
        $rows = $this->readRows(Storage::disk('local')->path($file->stored_path));
        $row = $rows[0] ?? null;

        if ($row === null) {
            $file->update(['row_count' => 0, 'accepted_count' => 0, 'rejected_count' => 0]);

            throw new RuntimeException('inventory_and_locations.csv contains no data row.');
        }

        [, $percentMultiLocationStock] = $this->parseOptionalFloat($row['percent_multi_location_stock'] ?? null);
        [, $percentLowOrZeroStock] = $this->parseOptionalFloat($row['percent_low_or_zero_stock'] ?? null);
        [, $skuCompleteness] = $this->parseOptionalFloat($row['sku_completeness'] ?? null);
        $exchangeAvailabilityRisk = $this->nullableString($row['exchange_availability_risk'] ?? null);
        [, $activeLocationCount] = $this->parseOptionalInt($row['active_location_count'] ?? null);
        $operationalComplexityScore = $this->nullableString($row['operational_complexity_score'] ?? null);

        $allBlank = $percentMultiLocationStock === null
            && $percentLowOrZeroStock === null
            && $skuCompleteness === null
            && $exchangeAvailabilityRisk === null
            && $activeLocationCount === null
            && $operationalComplexityScore === null;

        if ($allBlank) {
            $file->update(['row_count' => 1, 'accepted_count' => 0, 'rejected_count' => 1]);

            throw new RuntimeException('inventory_and_locations.csv row is entirely empty or unparseable.');
        }

        $assessment = $dataImport->assessment;

        DB::transaction(function () use ($dataImport, $assessment, $percentMultiLocationStock, $percentLowOrZeroStock, $skuCompleteness, $exchangeAvailabilityRisk, $activeLocationCount, $operationalComplexityScore): void {
            MerchantInventoryMetric::query()->where('source_import_id', $dataImport->id)->delete();
            MerchantLocationMetric::query()->where('source_import_id', $dataImport->id)->delete();

            MerchantInventoryMetric::create([
                'merchant_id' => $assessment->merchant_id,
                'assessment_id' => $assessment->id,
                'source_provider' => 'csv',
                'source_import_id' => $dataImport->id,
                'percent_multi_location_stock' => $percentMultiLocationStock,
                'percent_low_or_zero_stock' => $percentLowOrZeroStock,
                'sku_completeness' => $skuCompleteness,
                'exchange_availability_risk' => $exchangeAvailabilityRisk,
            ]);

            MerchantLocationMetric::create([
                'merchant_id' => $assessment->merchant_id,
                'assessment_id' => $assessment->id,
                'source_provider' => 'csv',
                'source_import_id' => $dataImport->id,
                'active_location_count' => $activeLocationCount,
                'operational_complexity_score' => $operationalComplexityScore,
            ]);
        });

        $file->update(['row_count' => 1, 'accepted_count' => 1, 'rejected_count' => 0]);
    }
}
