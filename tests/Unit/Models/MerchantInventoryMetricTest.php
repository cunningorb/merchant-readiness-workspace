<?php

namespace Tests\Unit\Models;

use App\Models\Assessment;
use App\Models\DataImport;
use App\Models\Merchant;
use App\Models\MerchantInventoryMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantInventoryMetricTest extends TestCase
{
    use RefreshDatabase;

    public function test_metric_belongs_to_merchant(): void
    {
        $merchant = Merchant::factory()->create();
        $metric = MerchantInventoryMetric::factory()->for($merchant)->create();

        $this->assertTrue($metric->merchant->is($merchant));
    }

    public function test_metric_belongs_to_assessment(): void
    {
        $assessment = Assessment::factory()->create();
        $metric = MerchantInventoryMetric::factory()->for($assessment)->create();

        $this->assertTrue($metric->assessment->is($assessment));
    }

    public function test_deleting_source_import_nullifies_foreign_key(): void
    {
        $import = DataImport::factory()->create();
        $metric = MerchantInventoryMetric::factory()->create(['source_import_id' => $import->id]);

        $import->delete();

        $this->assertNull($metric->fresh()->source_import_id);
        $this->assertDatabaseHas('merchant_inventory_metrics', ['id' => $metric->id]);
    }

    public function test_percentage_columns_cast_to_decimal_strings(): void
    {
        $metric = MerchantInventoryMetric::factory()->create([
            'percent_multi_location_stock' => 42.5,
            'percent_low_or_zero_stock' => 10,
            'sku_completeness' => 95.25,
        ]);

        $fresh = $metric->fresh();

        $this->assertSame('42.50', $fresh->percent_multi_location_stock);
        $this->assertSame('10.00', $fresh->percent_low_or_zero_stock);
        $this->assertSame('95.25', $fresh->sku_completeness);
    }

    public function test_factory_creates_valid_metric(): void
    {
        $metric = MerchantInventoryMetric::factory()->create();

        $this->assertDatabaseHas('merchant_inventory_metrics', ['id' => $metric->id]);
    }
}
