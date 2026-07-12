<?php

namespace App\Services\Imports\Demo;

use App\Contracts\OrderReturnImporter;
use App\Models\DataImport;
use App\Models\MerchantOrderMetric;
use App\Models\MerchantReturnMetric;
use Illuminate\Support\Carbon;

/**
 * Writes the order-volume and return-rate metric rows for a demo scenario,
 * covering a synthetic 30-day period ending today.
 */
class DemoOrderReturnImporter implements OrderReturnImporter
{
    use ResolvesDemoScenario;

    public function importOrdersAndReturns(DataImport $dataImport): void
    {
        $scenario = $this->scenarioFor($dataImport);
        $data = DemoScenarios::for($scenario);
        $assessment = $dataImport->assessment;

        $periodEnd = Carbon::now();
        $periodStart = $periodEnd->copy()->subDays(30);

        MerchantOrderMetric::create([
            'merchant_id' => $assessment->merchant_id,
            'assessment_id' => $assessment->id,
            'source_provider' => 'demo',
            'source_import_id' => $dataImport->id,
            'source_period_start' => $periodStart,
            'source_period_end' => $periodEnd,
            'order_count' => $data['order_metric']['order_count'],
            'annualized_order_volume' => $data['order_metric']['annualized_order_volume'],
            'average_order_value' => $data['order_metric']['average_order_value'],
        ]);

        MerchantReturnMetric::create([
            'merchant_id' => $assessment->merchant_id,
            'assessment_id' => $assessment->id,
            'source_provider' => 'demo',
            'source_import_id' => $dataImport->id,
            'source_period_start' => $periodStart,
            'source_period_end' => $periodEnd,
            'refund_amount_total' => $data['return_metric']['refund_amount_total'],
            'refund_units_total' => $data['return_metric']['refund_units_total'],
            'estimated_refund_rate' => $data['return_metric']['estimated_refund_rate'],
            'exchange_share' => $data['return_metric']['exchange_share'],
            'refund_only_share' => $data['return_metric']['refund_only_share'],
            'average_time_to_refund_days' => $data['return_metric']['average_time_to_refund_days'],
            'top_refund_categories' => $data['return_metric']['top_refund_categories'],
        ]);
    }
}
