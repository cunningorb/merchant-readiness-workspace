<?php

namespace App\Services\Imports\Demo;

use App\Contracts\InventoryImporter;
use App\Models\DataImport;
use App\Models\MerchantInventoryMetric;
use App\Models\MerchantLocationMetric;
use Illuminate\Support\Facades\DB;

/**
 * Writes the inventory-health and location-footprint metric rows for a demo
 * scenario. The 'inventory_locations' data type covers both metric tables in
 * a single pass, matching the coordinator's single job for this data type.
 */
class DemoInventoryImporter implements InventoryImporter
{
    use ResolvesDemoScenario;

    public function importInventory(DataImport $dataImport): void
    {
        $scenario = $this->scenarioFor($dataImport);
        $data = DemoScenarios::for($scenario);
        $assessment = $dataImport->assessment;

        DB::transaction(function () use ($dataImport, $assessment, $data): void {
            MerchantInventoryMetric::query()->where('source_import_id', $dataImport->id)->delete();
            MerchantLocationMetric::query()->where('source_import_id', $dataImport->id)->delete();

            MerchantInventoryMetric::create([
                'merchant_id' => $assessment->merchant_id,
                'assessment_id' => $assessment->id,
                'source_provider' => 'demo',
                'source_import_id' => $dataImport->id,
                'percent_multi_location_stock' => $data['inventory_metric']['percent_multi_location_stock'],
                'percent_low_or_zero_stock' => $data['inventory_metric']['percent_low_or_zero_stock'],
                'sku_completeness' => $data['inventory_metric']['sku_completeness'],
                'exchange_availability_risk' => $data['inventory_metric']['exchange_availability_risk'],
            ]);

            MerchantLocationMetric::create([
                'merchant_id' => $assessment->merchant_id,
                'assessment_id' => $assessment->id,
                'source_provider' => 'demo',
                'source_import_id' => $dataImport->id,
                'active_location_count' => $data['location_metric']['active_location_count'],
                'operational_complexity_score' => $data['location_metric']['operational_complexity_score'],
            ]);
        });
    }
}
