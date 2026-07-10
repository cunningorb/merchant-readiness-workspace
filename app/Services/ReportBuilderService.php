<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Report;

class ReportBuilderService
{
    public function createForAssessment(Assessment $assessment): Report
    {
        return $assessment->report()->create([
            'published_at' => now(),
        ]);
    }

    public function buildPayload(Report $report): array
    {
        $assessment = $report->assessment;
        $assessment->loadMissing(['recommendations', 'merchant', 'answers']);

        return [
            'merchant' => $this->merchantProfile($assessment),
            'assessment' => [
                'overall_score' => $assessment->overall_score,
                'overall_tier' => $assessment->overall_tier,
                'section_scores' => $assessment->section_scores,
                'ranked_sections' => collect($assessment->section_scores)->sortBy('score')->all(),
            ],
            'recommendations' => $assessment->recommendations->map(fn ($recommendation) => [
                'title' => $recommendation->title,
                'description' => $recommendation->description,
                'category' => $recommendation->category,
                'priority' => $recommendation->priority,
                'expected_impact' => $recommendation->expected_impact,
            ])->all(),
            'published_at' => $report->published_at,
        ];
    }

    private function merchantProfile(Assessment $assessment): array
    {
        $optional = array_filter([
            'monthly_order_volume' => $assessment->answerValue('business.monthly_order_volume'),
            'sku_count' => $assessment->answerValue('catalog.sku_count'),
            'ecommerce_platform' => $assessment->answerValue('platform.ecommerce_platform'),
        ], fn ($value) => $value !== null && $value !== '');

        return array_merge(['company_name' => $assessment->merchant->company_name], $optional);
    }
}
