<?php

namespace App\Services\Imports\Csv;

use App\Contracts\OrderReturnImporter;
use App\Models\DataImport;
use App\Models\MerchantOrderMetric;
use App\Models\MerchantReturnMetric;
use App\Services\Imports\Csv\Concerns\LocatesDataImportFile;
use App\Services\Imports\Csv\Concerns\ParsesCsvValues;
use App\Services\Imports\Csv\Concerns\ReadsCsvRows;
use App\Services\Imports\ImportCoordinator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Parses orders_and_returns.csv — a deliberate Milestone 11 simplification:
 * ONE pre-aggregated summary row a merchant fills in directly (dates as
 * YYYY-MM-DD), not raw per-order transaction rows. Computing refund
 * rate/exchange share from raw order line items is real business logic
 * that belongs with Milestone 12's actual Shopify order/return
 * aggregation, not this milestone's framework-proving CSV path.
 *
 * There's only one row, so a missing/malformed required field
 * (period_start, period_end, order_count) rejects the whole file — this
 * throws, which ProcessOrderReturnImportJob catches and records as a
 * DataImportError, failing this data type without failing an import that
 * has other, successfully-imported data types attached. Other optional
 * numeric fields default to null when blank or unparseable rather than
 * rejecting the file.
 */
class CsvOrderReturnImporter implements OrderReturnImporter
{
    use LocatesDataImportFile, ParsesCsvValues, ReadsCsvRows;

    public function importOrdersAndReturns(DataImport $dataImport): void
    {
        $file = $this->fileFor($dataImport, ImportCoordinator::DATA_TYPE_ORDERS_RETURNS);
        $rows = $this->readRows(Storage::disk('local')->path($file->stored_path));
        $row = $rows[0] ?? null;

        if ($row === null) {
            $file->update(['row_count' => 0, 'accepted_count' => 0, 'rejected_count' => 0]);

            throw new RuntimeException('orders_and_returns.csv contains no data row.');
        }

        $periodStart = $this->nullableString($row['period_start'] ?? null);
        $periodEnd = $this->nullableString($row['period_end'] ?? null);
        [$orderCountOk, $orderCount] = $this->parseOptionalInt($row['order_count'] ?? null);

        $errors = [];
        if ($periodStart === null || ! $this->isValidDate($periodStart)) {
            $errors[] = "'period_start' is missing or not a valid YYYY-MM-DD date.";
        }
        if ($periodEnd === null || ! $this->isValidDate($periodEnd)) {
            $errors[] = "'period_end' is missing or not a valid YYYY-MM-DD date.";
        }
        if (! $orderCountOk || $orderCount === null) {
            $errors[] = "'order_count' is missing or not a valid number.";
        }

        if ($errors !== []) {
            $file->update(['row_count' => 1, 'accepted_count' => 0, 'rejected_count' => 1]);

            throw new RuntimeException('orders_and_returns.csv rejected: '.implode(' ', $errors));
        }

        [, $annualizedOrderVolume] = $this->parseOptionalInt($row['annualized_order_volume'] ?? null);
        [, $averageOrderValue] = $this->parseOptionalFloat($row['average_order_value'] ?? null);
        [, $refundAmountTotal] = $this->parseOptionalFloat($row['refund_amount_total'] ?? null);
        [, $refundUnitsTotal] = $this->parseOptionalInt($row['refund_units_total'] ?? null);
        [, $estimatedRefundRate] = $this->parseOptionalFloat($row['estimated_refund_rate'] ?? null);
        [, $exchangeShare] = $this->parseOptionalFloat($row['exchange_share'] ?? null);
        [, $refundOnlyShare] = $this->parseOptionalFloat($row['refund_only_share'] ?? null);
        [, $averageTimeToRefundDays] = $this->parseOptionalFloat($row['average_time_to_refund_days'] ?? null);

        $assessment = $dataImport->assessment;

        DB::transaction(function () use ($dataImport, $assessment, $periodStart, $periodEnd, $orderCount, $annualizedOrderVolume, $averageOrderValue, $refundAmountTotal, $refundUnitsTotal, $estimatedRefundRate, $exchangeShare, $refundOnlyShare, $averageTimeToRefundDays): void {
            MerchantOrderMetric::query()->where('source_import_id', $dataImport->id)->delete();
            MerchantReturnMetric::query()->where('source_import_id', $dataImport->id)->delete();

            MerchantOrderMetric::create([
                'merchant_id' => $assessment->merchant_id,
                'assessment_id' => $assessment->id,
                'source_provider' => 'csv',
                'source_import_id' => $dataImport->id,
                'source_period_start' => $periodStart,
                'source_period_end' => $periodEnd,
                'order_count' => $orderCount,
                'annualized_order_volume' => $annualizedOrderVolume,
                'average_order_value' => $averageOrderValue,
            ]);

            MerchantReturnMetric::create([
                'merchant_id' => $assessment->merchant_id,
                'assessment_id' => $assessment->id,
                'source_provider' => 'csv',
                'source_import_id' => $dataImport->id,
                'source_period_start' => $periodStart,
                'source_period_end' => $periodEnd,
                'refund_amount_total' => $refundAmountTotal,
                'refund_units_total' => $refundUnitsTotal,
                'estimated_refund_rate' => $estimatedRefundRate,
                'exchange_share' => $exchangeShare,
                'refund_only_share' => $refundOnlyShare,
                'average_time_to_refund_days' => $averageTimeToRefundDays,
            ]);
        });

        $file->update(['row_count' => 1, 'accepted_count' => 1, 'rejected_count' => 0]);
    }
}
