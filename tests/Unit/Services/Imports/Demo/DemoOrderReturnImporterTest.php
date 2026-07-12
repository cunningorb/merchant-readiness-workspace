<?php

namespace Tests\Unit\Services\Imports\Demo;

use App\Enums\ImportMethod;
use App\Enums\ImportStatus;
use App\Models\Assessment;
use App\Models\DataImport;
use App\Models\Merchant;
use App\Models\MerchantOrderMetric;
use App\Models\MerchantReturnMetric;
use App\Services\Imports\Demo\DemoOrderReturnImporter;
use App\Services\Imports\Demo\DemoScenarios;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoOrderReturnImporterTest extends TestCase
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

    public function test_apparel_scenario_writes_the_exact_order_and_return_metrics(): void
    {
        $dataImport = $this->importFor(DemoScenarios::APPAREL);

        (new DemoOrderReturnImporter)->importOrdersAndReturns($dataImport);

        $order = MerchantOrderMetric::query()->where('source_import_id', $dataImport->id)->firstOrFail();
        $this->assertSame(480, $order->order_count);
        $this->assertSame(5760, $order->annualized_order_volume);
        $this->assertSame('78.50', $order->average_order_value);
        $this->assertSame('demo', $order->source_provider);
        $this->assertSame($dataImport->assessment->merchant_id, $order->merchant_id);
        $this->assertSame($dataImport->assessment_id, $order->assessment_id);

        $return = MerchantReturnMetric::query()->where('source_import_id', $dataImport->id)->firstOrFail();
        $this->assertSame('0.1900', $return->estimated_refund_rate);
        $this->assertSame('0.0900', $return->exchange_share);
        $this->assertSame('0.8200', $return->refund_only_share);
        $this->assertSame('6.50', $return->average_time_to_refund_days);
        $this->assertSame(['Performance Leggings', 'Relaxed Denim Jacket'], $return->top_refund_categories);
        $this->assertSame(91, $return->refund_units_total);
        $this->assertSame('7143.50', $return->refund_amount_total);
        $this->assertSame('demo', $return->source_provider);
    }

    public function test_footwear_scenario_writes_the_exact_order_and_return_metrics(): void
    {
        $dataImport = $this->importFor(DemoScenarios::FOOTWEAR);

        (new DemoOrderReturnImporter)->importOrdersAndReturns($dataImport);

        $order = MerchantOrderMetric::query()->where('source_import_id', $dataImport->id)->firstOrFail();
        $this->assertSame(920, $order->order_count);
        $this->assertSame(11040, $order->annualized_order_volume);
        $this->assertSame('112.00', $order->average_order_value);

        $return = MerchantReturnMetric::query()->where('source_import_id', $dataImport->id)->firstOrFail();
        $this->assertSame('0.2400', $return->estimated_refund_rate);
        $this->assertSame('0.2800', $return->exchange_share);
        $this->assertSame('0.6600', $return->refund_only_share);
        $this->assertSame('5.00', $return->average_time_to_refund_days);
        $this->assertSame(['Trail Runner', 'All-Terrain Boot'], $return->top_refund_categories);
        $this->assertSame(221, $return->refund_units_total);
        $this->assertSame('24752.00', $return->refund_amount_total);
    }

    public function test_home_goods_scenario_writes_the_exact_order_and_return_metrics(): void
    {
        $dataImport = $this->importFor(DemoScenarios::HOME_GOODS);

        (new DemoOrderReturnImporter)->importOrdersAndReturns($dataImport);

        $order = MerchantOrderMetric::query()->where('source_import_id', $dataImport->id)->firstOrFail();
        $this->assertSame(140, $order->order_count);
        $this->assertSame(1680, $order->annualized_order_volume);
        $this->assertSame('145.00', $order->average_order_value);

        $return = MerchantReturnMetric::query()->where('source_import_id', $dataImport->id)->firstOrFail();
        $this->assertSame('0.0800', $return->estimated_refund_rate);
        $this->assertSame('0.0300', $return->exchange_share);
        $this->assertSame('0.9500', $return->refund_only_share);
        $this->assertSame('9.00', $return->average_time_to_refund_days);
        $this->assertSame(['Oak Side Table'], $return->top_refund_categories);
        $this->assertSame(11, $return->refund_units_total);
        $this->assertSame('1595.00', $return->refund_amount_total);
    }

    public function test_period_spans_thirty_days_ending_today(): void
    {
        $dataImport = $this->importFor(DemoScenarios::APPAREL);

        (new DemoOrderReturnImporter)->importOrdersAndReturns($dataImport);

        $order = MerchantOrderMetric::query()->where('source_import_id', $dataImport->id)->firstOrFail();

        $this->assertSame(now()->toDateString(), $order->source_period_end->toDateString());
        $this->assertSame(now()->subDays(30)->toDateString(), $order->source_period_start->toDateString());
    }
}
