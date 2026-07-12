<?php

namespace App\Services\Imports;

use App\Enums\ImportStatus;
use App\Models\Assessment;
use App\Models\DataImport;
use App\Models\MerchantOrderMetric;
use App\Models\MerchantProduct;

/**
 * Merges explicit questionnaire answers with values derived from a merchant's
 * latest usable data import, without ever letting an imported value silently
 * overwrite an explicit answer.
 *
 * Standalone primitive: nothing consumes this yet. A future milestone wires
 * it into scoring, opportunities, and benchmarks.
 */
class AssessmentEvidenceService
{
    private const USABLE_STATUSES = [
        ImportStatus::Completed,
        ImportStatus::CompletedWithWarnings,
    ];

    /**
     * @return array<string, EvidenceRecord>
     */
    public function mergedEvidence(Assessment $assessment): array
    {
        $latestImport = $this->latestUsableImport($assessment);

        return [
            'business.monthly_order_volume' => new EvidenceRecord(
                key: 'business.monthly_order_volume',
                answerValue: $assessment->answerValue('business.monthly_order_volume'),
                importedValue: $this->monthlyOrderVolumeFromImport($assessment, $latestImport),
            ),
            'catalog.sku_count' => new EvidenceRecord(
                key: 'catalog.sku_count',
                answerValue: $assessment->answerValue('catalog.sku_count'),
                importedValue: $this->skuCountFromImport($assessment, $latestImport),
            ),
        ];
    }

    public function latestUsableImport(Assessment $assessment): ?DataImport
    {
        return $assessment->dataImports()
            ->whereIn('status', array_map(fn (ImportStatus $status) => $status->value, self::USABLE_STATUSES))
            ->orderByDesc('id')
            ->first();
    }

    private function monthlyOrderVolumeFromImport(Assessment $assessment, ?DataImport $latestImport): ?int
    {
        if ($latestImport === null) {
            return null;
        }

        $metric = MerchantOrderMetric::query()
            ->where('assessment_id', $assessment->id)
            ->where('source_import_id', $latestImport->id)
            ->first();

        if ($metric === null || $metric->annualized_order_volume === null) {
            return null;
        }

        return (int) round($metric->annualized_order_volume / 12);
    }

    private function skuCountFromImport(Assessment $assessment, ?DataImport $latestImport): ?int
    {
        if ($latestImport === null) {
            return null;
        }

        $count = MerchantProduct::query()
            ->where('merchant_id', $assessment->merchant_id)
            ->where('source_import_id', $latestImport->id)
            ->count();

        return $count > 0 ? $count : null;
    }
}
