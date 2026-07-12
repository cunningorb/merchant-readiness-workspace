<?php

namespace Tests\Unit\Models;

use App\Models\Assessment;
use App\Models\DataImport;
use App\Models\Merchant;
use App\Models\MerchantReturnMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantReturnMetricTest extends TestCase
{
    use RefreshDatabase;

    public function test_metric_belongs_to_merchant(): void
    {
        $merchant = Merchant::factory()->create();
        $metric = MerchantReturnMetric::factory()->for($merchant)->create();

        $this->assertTrue($metric->merchant->is($merchant));
    }

    public function test_metric_belongs_to_assessment(): void
    {
        $assessment = Assessment::factory()->create();
        $metric = MerchantReturnMetric::factory()->for($assessment)->create();

        $this->assertTrue($metric->assessment->is($assessment));
    }

    public function test_deleting_source_import_nullifies_foreign_key(): void
    {
        $import = DataImport::factory()->create();
        $metric = MerchantReturnMetric::factory()->create(['source_import_id' => $import->id]);

        $import->delete();

        $this->assertNull($metric->fresh()->source_import_id);
        $this->assertDatabaseHas('merchant_return_metrics', ['id' => $metric->id]);
    }

    public function test_top_refund_categories_casts_to_array(): void
    {
        $metric = MerchantReturnMetric::factory()->create(['top_refund_categories' => ['sizing', 'damaged']]);

        $this->assertSame(['sizing', 'damaged'], $metric->fresh()->top_refund_categories);
    }

    public function test_decimal_columns_cast_correctly(): void
    {
        $metric = MerchantReturnMetric::factory()->create([
            'refund_amount_total' => 1234.5,
            'estimated_refund_rate' => 0.1234,
            'exchange_share' => 0.25,
            'refund_only_share' => 0.75,
            'average_time_to_refund_days' => 3.5,
        ]);

        $fresh = $metric->fresh();

        $this->assertSame('1234.50', $fresh->refund_amount_total);
        $this->assertSame('0.1234', $fresh->estimated_refund_rate);
        $this->assertSame('0.2500', $fresh->exchange_share);
        $this->assertSame('0.7500', $fresh->refund_only_share);
        $this->assertSame('3.50', $fresh->average_time_to_refund_days);
    }

    public function test_factory_creates_valid_metric(): void
    {
        $metric = MerchantReturnMetric::factory()->create();

        $this->assertDatabaseHas('merchant_return_metrics', ['id' => $metric->id]);
    }
}
