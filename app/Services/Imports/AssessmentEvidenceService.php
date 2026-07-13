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
        $latest = $assessment->dataImports()
            ->whereIn('status', array_map(fn (ImportStatus $status) => $status->value, self::USABLE_STATUSES))
            ->orderByDesc('id')
            ->first();

        return $this->resolveOriginal($latest);
    }

    /**
     * A duplicate-detected import is stamped completed but writes no metric/
     * product rows of its own — its evidence lives under the ORIGINAL import it
     * duplicated (metadata.duplicate_of_import_id). Follow that pointer, guarding
     * against a malformed cycle, so the queries below scope to the id that
     * actually owns the rows rather than the empty duplicate stub.
     */
    private function resolveOriginal(?DataImport $import): ?DataImport
    {
        $seen = [];

        while ($import !== null) {
            $duplicateOf = $import->metadata['duplicate_of_import_id'] ?? null;

            if ($duplicateOf === null || in_array($import->id, $seen, true)) {
                return $import;
            }

            $seen[] = $import->id;
            $original = DataImport::find($duplicateOf);

            if ($original === null) {
                return $import;
            }

            $import = $original;
        }

        return $import;
    }

    private function monthlyOrderVolumeFromImport(Assessment $assessment, ?DataImport $latestImport): ?int
    {
        if ($latestImport === null) {
            return null;
        }

        // Scope to the resolved import's own assessment, not necessarily the one
        // being evaluated: when $latestImport is the original behind a duplicate
        // stub, its metric rows belong to the assessment that first imported them
        // (a different assessment for the same merchant).
        $metric = MerchantOrderMetric::query()
            ->where('assessment_id', $latestImport->assessment_id)
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
