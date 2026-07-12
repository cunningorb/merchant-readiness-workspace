<?php

namespace Tests\Unit\Services\Imports\Demo;

use App\Enums\ImportStatus;
use App\Models\Assessment;
use App\Models\Merchant;
use App\Models\MerchantInventoryMetric;
use App\Models\MerchantLocationMetric;
use App\Models\MerchantOrderMetric;
use App\Models\MerchantProduct;
use App\Models\MerchantReturnMetric;
use App\Services\Imports\AssessmentEvidenceService;
use App\Services\Imports\Demo\DemoDataProvider;
use App\Services\Imports\Demo\DemoScenarios;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataProviderTest extends TestCase
{
    use RefreshDatabase;

    private function assessment(): Assessment
    {
        return Assessment::factory()->create([
            'merchant_id' => Merchant::factory()->create()->id,
        ]);
    }

    // --- MerchantDataProvider contract --------------------------------------

    public function test_provider_identifies_itself_as_demo(): void
    {
        $this->assertSame('demo', (new DemoDataProvider)->provider());
    }

    public function test_supports_all_three_data_types(): void
    {
        $provider = new DemoDataProvider;

        $this->assertTrue($provider->supports('catalog'));
        $this->assertTrue($provider->supports('orders_returns'));
        $this->assertTrue($provider->supports('inventory_locations'));
    }

    public function test_does_not_support_an_unknown_data_type(): void
    {
        $this->assertFalse((new DemoDataProvider)->supports('not_a_real_type'));
    }

    // --- startImport() end-to-end -------------------------------------------

    public function test_start_import_rejects_an_unknown_scenario(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DemoDataProvider::startImport($this->assessment(), 'not_a_scenario');
    }

    public function test_start_import_completes_successfully_for_apparel(): void
    {
        $assessment = $this->assessment();

        $import = DemoDataProvider::startImport($assessment, DemoScenarios::APPAREL);

        $this->assertSame(ImportStatus::Completed->value, $import->status);
        $this->assertSame('demo', $import->provider);
        $this->assertSame(
            ['catalog', 'orders_returns', 'inventory_locations'],
            $import->data_types,
        );
        $this->assertSame(DemoScenarios::APPAREL, $import->metadata['demo_scenario']);
        $this->assertSame(6, MerchantProduct::query()->where('source_import_id', $import->id)->count());
        $this->assertSame(1, MerchantOrderMetric::query()->where('source_import_id', $import->id)->count());
        $this->assertSame(1, MerchantReturnMetric::query()->where('source_import_id', $import->id)->count());
        $this->assertSame(1, MerchantInventoryMetric::query()->where('source_import_id', $import->id)->count());
        $this->assertSame(1, MerchantLocationMetric::query()->where('source_import_id', $import->id)->count());
    }

    public function test_start_import_completes_successfully_for_footwear(): void
    {
        $import = DemoDataProvider::startImport($this->assessment(), DemoScenarios::FOOTWEAR);

        $this->assertSame(ImportStatus::Completed->value, $import->status);
        $this->assertSame(5, MerchantProduct::query()->where('source_import_id', $import->id)->count());
    }

    public function test_start_import_completes_successfully_for_home_goods(): void
    {
        $import = DemoDataProvider::startImport($this->assessment(), DemoScenarios::HOME_GOODS);

        $this->assertSame(ImportStatus::Completed->value, $import->status);
        $this->assertSame(4, MerchantProduct::query()->where('source_import_id', $import->id)->count());
    }

    public function test_start_import_does_not_short_circuit_on_a_second_identical_scenario(): void
    {
        $assessment = $this->assessment();

        $first = DemoDataProvider::startImport($assessment, DemoScenarios::APPAREL);
        $second = DemoDataProvider::startImport($assessment, DemoScenarios::APPAREL);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(ImportStatus::Completed->value, $second->status);
        $this->assertArrayNotHasKey('duplicate_of_import_id', $second->metadata);
        // Both imports' products persist independently — no dedupe for demo data.
        $this->assertSame(6, MerchantProduct::query()->where('source_import_id', $first->id)->count());
        $this->assertSame(6, MerchantProduct::query()->where('source_import_id', $second->id)->count());
    }

    // --- Task 2 + Task 4 integration ----------------------------------------

    public function test_assessment_evidence_service_picks_up_demo_imported_order_volume_and_sku_count(): void
    {
        $assessment = $this->assessment();

        DemoDataProvider::startImport($assessment, DemoScenarios::APPAREL);

        $evidence = (new AssessmentEvidenceService)->mergedEvidence($assessment->fresh(['answers']));

        $orderVolume = $evidence['business.monthly_order_volume'];
        $this->assertSame('imported', $orderVolume->source);
        // annualized_order_volume 5760 / 12 = 480
        $this->assertSame(480, $orderVolume->importedValue);
        $this->assertSame(480, $orderVolume->authoritativeValue);

        $skuCount = $evidence['catalog.sku_count'];
        $this->assertSame('imported', $skuCount->source);
        $this->assertSame(6, $skuCount->importedValue);
        $this->assertSame(6, $skuCount->authoritativeValue);
    }
}
