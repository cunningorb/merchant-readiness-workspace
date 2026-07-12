<?php

namespace Tests\Unit\Models;

use App\Models\Assessment;
use App\Models\DataImport;
use App\Models\Merchant;
use App\Models\MerchantLocationMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantLocationMetricTest extends TestCase
{
    use RefreshDatabase;

    public function test_metric_belongs_to_merchant(): void
    {
        $merchant = Merchant::factory()->create();
        $metric = MerchantLocationMetric::factory()->for($merchant)->create();

        $this->assertTrue($metric->merchant->is($merchant));
    }

    public function test_metric_belongs_to_assessment(): void
    {
        $assessment = Assessment::factory()->create();
        $metric = MerchantLocationMetric::factory()->for($assessment)->create();

        $this->assertTrue($metric->assessment->is($assessment));
    }

    public function test_deleting_source_import_nullifies_foreign_key(): void
    {
        $import = DataImport::factory()->create();
        $metric = MerchantLocationMetric::factory()->create(['source_import_id' => $import->id]);

        $import->delete();

        $this->assertNull($metric->fresh()->source_import_id);
        $this->assertDatabaseHas('merchant_location_metrics', ['id' => $metric->id]);
    }

    public function test_active_location_count_is_nullable(): void
    {
        $metric = MerchantLocationMetric::factory()->create(['active_location_count' => null]);

        $this->assertNull($metric->fresh()->active_location_count);
    }

    public function test_factory_creates_valid_metric(): void
    {
        $metric = MerchantLocationMetric::factory()->create();

        $this->assertDatabaseHas('merchant_location_metrics', ['id' => $metric->id]);
    }
}
