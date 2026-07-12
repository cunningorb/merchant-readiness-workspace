<?php

namespace Tests\Unit\Services\Imports\Csv;

use App\Enums\ImportMethod;
use App\Models\Assessment;
use App\Models\DataImport;
use App\Models\DataImportFile;
use App\Models\Merchant;
use App\Models\MerchantOrderMetric;
use App\Models\MerchantReturnMetric;
use App\Services\Imports\Csv\CsvOrderReturnImporter;
use App\Services\Imports\ImportCoordinator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class CsvOrderReturnImporterTest extends TestCase
{
    use RefreshDatabase;

    private function importWithFile(string $csv): array
    {
        Storage::fake('local');

        $assessment = Assessment::factory()->create([
            'merchant_id' => Merchant::factory()->create()->id,
        ]);

        $dataImport = DataImport::factory()->for($assessment)->create([
            'provider' => 'csv',
            'method' => ImportMethod::Csv->value,
        ]);

        $path = "imports/{$dataImport->id}/orders_returns-orders_and_returns.csv";
        Storage::disk('local')->put($path, $csv);

        $file = DataImportFile::factory()->for($dataImport)->create([
            'data_type' => ImportCoordinator::DATA_TYPE_ORDERS_RETURNS,
            'stored_path' => $path,
            'original_filename' => 'orders_and_returns.csv',
            'row_count' => null,
            'accepted_count' => null,
            'rejected_count' => null,
            'fingerprint' => hash('sha256', $csv),
        ]);

        return [$dataImport, $file];
    }

    public function test_well_formed_row_parses_into_order_and_return_metrics(): void
    {
        $csv = <<<'CSV'
        period_start,period_end,order_count,annualized_order_volume,average_order_value,refund_amount_total,refund_units_total,estimated_refund_rate,exchange_share,refund_only_share,average_time_to_refund_days
        2026-01-01,2026-01-31,500,6000,85.50,12500.75,150,0.18,0.20,0.75,5.25
        CSV;

        [$dataImport, $file] = $this->importWithFile($csv);

        (new CsvOrderReturnImporter)->importOrdersAndReturns($dataImport);

        $order = MerchantOrderMetric::query()->where('source_import_id', $dataImport->id)->firstOrFail();
        $this->assertSame('csv', $order->source_provider);
        $this->assertSame($dataImport->assessment->merchant_id, $order->merchant_id);
        $this->assertSame($dataImport->assessment_id, $order->assessment_id);
        $this->assertSame('2026-01-01', $order->source_period_start->toDateString());
        $this->assertSame('2026-01-31', $order->source_period_end->toDateString());
        $this->assertSame(500, $order->order_count);
        $this->assertSame(6000, $order->annualized_order_volume);
        $this->assertSame('85.50', $order->average_order_value);

        $return = MerchantReturnMetric::query()->where('source_import_id', $dataImport->id)->firstOrFail();
        $this->assertSame('csv', $return->source_provider);
        $this->assertSame('12500.75', $return->refund_amount_total);
        $this->assertSame(150, $return->refund_units_total);
        $this->assertSame('0.1800', $return->estimated_refund_rate);
        $this->assertSame('0.2000', $return->exchange_share);
        $this->assertSame('0.7500', $return->refund_only_share);
        $this->assertSame('5.25', $return->average_time_to_refund_days);

        $file = $file->fresh();
        $this->assertSame(1, $file->row_count);
        $this->assertSame(1, $file->accepted_count);
        $this->assertSame(0, $file->rejected_count);
    }

    public function test_blank_optional_numeric_fields_default_to_null_without_rejecting_the_file(): void
    {
        $csv = <<<'CSV'
        period_start,period_end,order_count,annualized_order_volume,average_order_value,refund_amount_total,refund_units_total,estimated_refund_rate,exchange_share,refund_only_share,average_time_to_refund_days
        2026-01-01,2026-01-31,500,,,,,,,,
        CSV;

        [$dataImport] = $this->importWithFile($csv);

        (new CsvOrderReturnImporter)->importOrdersAndReturns($dataImport);

        $order = MerchantOrderMetric::query()->where('source_import_id', $dataImport->id)->firstOrFail();
        $this->assertSame(500, $order->order_count);
        $this->assertNull($order->annualized_order_volume);
        $this->assertNull($order->average_order_value);

        $return = MerchantReturnMetric::query()->where('source_import_id', $dataImport->id)->firstOrFail();
        $this->assertNull($return->refund_amount_total);
        $this->assertNull($return->estimated_refund_rate);
    }

    public function test_missing_required_field_rejects_the_whole_file(): void
    {
        $csv = <<<'CSV'
        period_start,period_end,order_count,annualized_order_volume,average_order_value,refund_amount_total,refund_units_total,estimated_refund_rate,exchange_share,refund_only_share,average_time_to_refund_days
        ,2026-01-31,500,6000,85.50,12500.75,150,0.18,0.20,0.75,5.25
        CSV;

        [$dataImport, $file] = $this->importWithFile($csv);

        $this->expectException(RuntimeException::class);

        try {
            (new CsvOrderReturnImporter)->importOrdersAndReturns($dataImport);
        } finally {
            $this->assertSame(0, MerchantOrderMetric::query()->where('source_import_id', $dataImport->id)->count());
            $this->assertSame(0, MerchantReturnMetric::query()->where('source_import_id', $dataImport->id)->count());
            $this->assertSame(0, $file->fresh()->accepted_count);
            $this->assertSame(1, $file->fresh()->rejected_count);
        }
    }

    public function test_malformed_order_count_rejects_the_whole_file(): void
    {
        $csv = <<<'CSV'
        period_start,period_end,order_count,annualized_order_volume,average_order_value,refund_amount_total,refund_units_total,estimated_refund_rate,exchange_share,refund_only_share,average_time_to_refund_days
        2026-01-01,2026-01-31,not-a-number,6000,85.50,12500.75,150,0.18,0.20,0.75,5.25
        CSV;

        [$dataImport] = $this->importWithFile($csv);

        $this->expectException(RuntimeException::class);

        (new CsvOrderReturnImporter)->importOrdersAndReturns($dataImport);
    }
}
