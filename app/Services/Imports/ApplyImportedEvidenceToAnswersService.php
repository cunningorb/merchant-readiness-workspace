<?php

namespace App\Services\Imports;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Services\AssessmentQuestionCatalog;

class ApplyImportedEvidenceToAnswersService
{
    public function __construct(
        private readonly AssessmentEvidenceService $evidence,
        private readonly AssessmentQuestionCatalog $catalog,
    ) {}

    public function apply(Assessment $assessment): void
    {
        if ($assessment->status === 'submitted') {
            return;
        }

        foreach ($this->evidence->mergedEvidence($assessment->fresh(['answers'])) as $record) {
            if ($record->source !== 'imported') {
                continue;
            }

            $question = $this->catalog->question($record->key);
            $answerValue = $this->answerValue($record->key, $record->authoritativeValue);

            if ($question === null || $answerValue === null) {
                continue;
            }

            AssessmentAnswer::create([
                'assessment_id' => $assessment->id,
                'question_key' => $record->key,
                'section' => $question['section'],
                'value' => $answerValue,
            ]);
        }
    }

    private function answerValue(string $questionKey, mixed $value): mixed
    {
        return match ($questionKey) {
            'business.monthly_order_volume' => $this->monthlyOrderVolumeBand((int) $value),
            'catalog.sku_count' => $this->skuCountBand((int) $value),
            default => $value,
        };
    }

    private function monthlyOrderVolumeBand(int $ordersPerMonth): string
    {
        return match (true) {
            $ordersPerMonth < 1000 => 'Under 1,000',
            $ordersPerMonth <= 10000 => '1,000-10,000',
            $ordersPerMonth <= 50000 => '10,001-50,000',
            default => '50,000+',
        };
    }

    private function skuCountBand(int $skuCount): string
    {
        return match (true) {
            $skuCount < 500 => 'Under 500',
            $skuCount <= 5000 => '500-5,000',
            $skuCount <= 25000 => '5,001-25,000',
            default => '25,000+',
        };
    }
}
