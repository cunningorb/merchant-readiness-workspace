<?php

namespace Tests\Unit\Models;

use App\Models\Assessment;
use App\Models\DataImport;
use App\Models\Merchant;
use App\Models\MerchantOrderMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MerchantOrderMetricTest extends TestCase
{
    use RefreshDatabase;

    public function test_metric_belongs_to_merchant(): void
    {
        $merchant = Merchant::factory()->create();
        $metric = MerchantOrderMetric::factory()->for($merchant)->create();

        $this->assertTrue($metric->merchant->is($merchant));
    }

    public function test_metric_belongs_to_assessment(): void
    {
        $assessment = Assessment::factory()->create();
        $metric = MerchantOrderMetric::factory()->for($assessment)->create();

        $this->assertTrue($metric->assessment->is($assessment));
    }

    public function test_deleting_source_import_nullifies_foreign_key(): void
    {
        $import = DataImport::factory()->create();
        $metric = MerchantOrderMetric::factory()->create(['source_import_id' => $import->id]);

        $import->delete();

        $this->assertNull($metric->fresh()->source_import_id);
        $this->assertDatabaseHas('merchant_order_metrics', ['id' => $metric->id]);
    }

    public function test_period_dates_cast_to_date(): void
    {
        $metric = MerchantOrderMetric::factory()->create([
            'source_period_start' => '2026-01-01',
            'source_period_end' => '2026-06-30',
        ]);

        $fresh = $metric->fresh();

        $this->assertInstanceOf(Carbon::class, $fresh->source_period_start);
        $this->assertInstanceOf(Carbon::class, $fresh->source_period_end);
    }

    public function test_average_order_value_casts_to_decimal_string(): void
    {
        $metric = MerchantOrderMetric::factory()->create(['average_order_value' => 88.5]);

        $this->assertSame('88.50', $metric->fresh()->average_order_value);
    }

    public function test_factory_creates_valid_metric(): void
    {
        $metric = MerchantOrderMetric::factory()->create();

        $this->assertDatabaseHas('merchant_order_metrics', ['id' => $metric->id]);
    }
}
