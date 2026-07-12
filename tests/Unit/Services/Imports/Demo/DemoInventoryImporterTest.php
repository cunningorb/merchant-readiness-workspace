<?php

namespace Tests\Unit\Services\Imports\Demo;

use App\Enums\ImportMethod;
use App\Enums\ImportStatus;
use App\Models\Assessment;
use App\Models\DataImport;
use App\Models\Merchant;
use App\Models\MerchantInventoryMetric;
use App\Models\MerchantLocationMetric;
use App\Services\Imports\Demo\DemoInventoryImporter;
use App\Services\Imports\Demo\DemoScenarios;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoInventoryImporterTest extends TestCase
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

    public function test_apparel_scenario_writes_the_exact_inventory_and_location_metrics(): void
    {
        $dataImport = $this->importFor(DemoScenarios::APPAREL);

        (new DemoInventoryImporter)->importInventory($dataImport);

        $inventory = MerchantInventoryMetric::query()->where('source_import_id', $dataImport->id)->firstOrFail();
        $this->assertSame('35.00', $inventory->percent_multi_location_stock);
        $this->assertSame('22.00', $inventory->percent_low_or_zero_stock);
        $this->assertSame('88.00', $inventory->sku_completeness);
        $this->assertSame('medium', $inventory->exchange_availability_risk);
        $this->assertSame('demo', $inventory->source_provider);
        $this->assertSame($dataImport->assessment->merchant_id, $inventory->merchant_id);
        $this->assertSame($dataImport->assessment_id, $inventory->assessment_id);

        $location = MerchantLocationMetric::query()->where('source_import_id', $dataImport->id)->firstOrFail();
        $this->assertSame(2, $location->active_location_count);
        $this->assertSame('medium', $location->operational_complexity_score);
        $this->assertSame('demo', $location->source_provider);
    }

    public function test_footwear_scenario_writes_the_exact_inventory_and_location_metrics(): void
    {
        $dataImport = $this->importFor(DemoScenarios::FOOTWEAR);

        (new DemoInventoryImporter)->importInventory($dataImport);

        $inventory = MerchantInventoryMetric::query()->where('source_import_id', $dataImport->id)->firstOrFail();
        $this->assertSame('61.00', $inventory->percent_multi_location_stock);
        $this->assertSame('14.00', $inventory->percent_low_or_zero_stock);
        $this->assertSame('93.00', $inventory->sku_completeness);
        $this->assertSame('low', $inventory->exchange_availability_risk);

        $location = MerchantLocationMetric::query()->where('source_import_id', $dataImport->id)->firstOrFail();
        $this->assertSame(4, $location->active_location_count);
        $this->assertSame('high', $location->operational_complexity_score);
    }

    public function test_home_goods_scenario_writes_the_exact_inventory_and_location_metrics(): void
    {
        $dataImport = $this->importFor(DemoScenarios::HOME_GOODS);

        (new DemoInventoryImporter)->importInventory($dataImport);

        $inventory = MerchantInventoryMetric::query()->where('source_import_id', $dataImport->id)->firstOrFail();
        $this->assertSame('10.00', $inventory->percent_multi_location_stock);
        $this->assertSame('5.00', $inventory->percent_low_or_zero_stock);
        $this->assertSame('97.00', $inventory->sku_completeness);
        $this->assertSame('low', $inventory->exchange_availability_risk);

        $location = MerchantLocationMetric::query()->where('source_import_id', $dataImport->id)->firstOrFail();
        $this->assertSame(1, $location->active_location_count);
        $this->assertSame('low', $location->operational_complexity_score);
    }
}
