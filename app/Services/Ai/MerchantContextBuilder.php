<?php

namespace App\Services\Ai;

use App\Models\Assessment;
use App\Models\MerchantOrderMetric;
use App\Models\MerchantReturnMetric;
use App\Models\Recommendation;

/**
 * Builds the PII-free MerchantContext sent to the LLM. Only pre-aggregated
 * scalars and already-cleaned short evidence snippets ever cross this
 * boundary — never contact_email/contact_name, never a raw CSV row, never
 * raw website HTML.
 *
 * policy_score / automation_score / exchange_score / risk_score map onto
 * this app's four real scoring sections (return_policy, manual_operations,
 * exchanges, platform respectively) — "risk" here means platform/tooling
 * risk, since scoring has no dedicated risk section of its own.
 */
class MerchantContextBuilder
{
    private const EVIDENCE_SNIPPET_LIMIT = 160;

    public function __construct(
        private readonly MerchantPublicContextService $publicContext,
    ) {}

    public function build(Assessment $assessment, Recommendation $recommendation): MerchantContext
    {
        $assessment->loadMissing(['opportunities', 'benchmarkComparisons', 'answerEvidence', 'answers', 'merchant']);

        $sectionScores = $assessment->section_scores ?? [];
        $categoryMap = config('assessment.opportunities.recommendation_category_to_opportunity_type', []);
        $opportunityType = $categoryMap[$recommendation->category] ?? null;
        $linkedOpportunity = $opportunityType !== null
            ? $assessment->opportunities->firstWhere('type', $opportunityType)
            : null;

        $orderMetric = MerchantOrderMetric::query()->where('assessment_id', $assessment->id)->latest()->first();
        $returnMetric = MerchantReturnMetric::query()->where('assessment_id', $assessment->id)->latest()->first();

        $website = $assessment->merchant?->website;

        return new MerchantContext(
            companyName: $assessment->answerValue('business.company_name') ?? $assessment->merchant?->company_name,
            industry: $this->industry($assessment),
            website: $website,
            platform: $assessment->answerValue('platform.ecommerce_platform'),
            readinessScore: $assessment->overall_score,
            topOpportunities: $this->topOpportunities($assessment),
            topRecommendation: [
                'title' => $recommendation->title,
                'description' => $recommendation->description,
                'category' => $recommendation->category,
                'priority' => $recommendation->priority,
                'expected_impact' => $recommendation->expected_impact,
            ],
            manualHours: $assessment->answerValue('manual_operations.weekly_hours'),
            estimatedReturnRate: $returnMetric?->estimated_refund_rate !== null ? (float) $returnMetric->estimated_refund_rate : null,
            estimatedExchangeRate: $returnMetric?->exchange_share !== null ? (float) $returnMetric->exchange_share : null,
            estimatedOrderVolume: $orderMetric?->annualized_order_volume !== null ? (float) $orderMetric->annualized_order_volume : null,
            estimatedAov: $orderMetric?->average_order_value !== null ? (float) $orderMetric->average_order_value : null,
            catalogComplexity: $this->catalogComplexity($assessment),
            policyScore: $sectionScores['return_policy']['score'] ?? null,
            automationScore: $sectionScores['manual_operations']['score'] ?? null,
            exchangeScore: $sectionScores['exchanges']['score'] ?? null,
            riskScore: $sectionScores['platform']['score'] ?? null,
            peerBenchmarkSummary: $this->peerBenchmarkSummary($assessment),
            websiteScanSummary: $this->websiteScanSummary($assessment),
            evidenceSummaries: $this->evidenceSummaries($assessment, $recommendation),
            opportunityEstimate: $linkedOpportunity !== null ? [
                'type' => $linkedOpportunity->type,
                'minimum_value' => (float) $linkedOpportunity->minimum_value,
                'maximum_value' => (float) $linkedOpportunity->maximum_value,
                'unit' => $linkedOpportunity->unit,
                'confidence' => $linkedOpportunity->confidence,
                'effort' => $linkedOpportunity->effort,
            ] : null,
            confidence: $linkedOpportunity?->confidence ?? 'low',
            publicContext: $website !== null ? $this->publicContext->lookup($website) : null,
        );
    }

    private function industry(Assessment $assessment): ?string
    {
        $categories = $assessment->answerValue('catalog.fit_sensitive_categories');

        if (! is_array($categories) || $categories === []) {
            return null;
        }

        return implode(', ', $categories);
    }

    private function catalogComplexity(Assessment $assessment): ?string
    {
        $skuCount = $assessment->answerValue('catalog.sku_count');

        if ($skuCount === null) {
            return null;
        }

        $categories = $assessment->answerValue('catalog.fit_sensitive_categories');
        $categoryCount = is_array($categories) ? count($categories) : 0;

        if ($categoryCount === 0) {
            return "{$skuCount} SKUs";
        }

        $noun = $categoryCount === 1 ? 'category' : 'categories';

        return "{$skuCount} SKUs across {$categoryCount} fit-sensitive {$noun}";
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function topOpportunities(Assessment $assessment): array
    {
        return $assessment->opportunities->take(3)->map(fn ($opportunity) => [
            'type' => $opportunity->type,
            'title' => $opportunity->title,
            'minimum_value' => (float) $opportunity->minimum_value,
            'maximum_value' => (float) $opportunity->maximum_value,
            'unit' => $opportunity->unit,
            'confidence' => $opportunity->confidence,
        ])->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function peerBenchmarkSummary(Assessment $assessment): array
    {
        return $assessment->benchmarkComparisons->take(3)->map(fn ($comparison) => [
            'label' => $comparison->label,
            'interpretation' => $comparison->interpretation,
        ])->values()->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function websiteScanSummary(Assessment $assessment): ?array
    {
        $scan = $assessment->websiteScans()->latest('id')->first();

        if ($scan === null) {
            return null;
        }

        return [
            'pages_scanned' => $scan->pages_scanned,
            'signals_found' => count($scan->extracted_data ?? []),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function evidenceSummaries(Assessment $assessment, Recommendation $recommendation): array
    {
        $prefix = $recommendation->category.'.';

        return $assessment->answerEvidence
            ->filter(fn ($evidence) => str_starts_with($evidence->question_key, $prefix))
            ->take(3)
            ->map(fn ($evidence) => [
                'question_key' => $evidence->question_key,
                'source_label' => $evidence->source_label,
                'confidence' => $evidence->confidence,
                'summary' => $this->cleanSnippet($evidence->evidence_snippet),
            ])
            ->values()
            ->all();
    }

    private function cleanSnippet(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $stripped = trim(strip_tags($text));

        return mb_strlen($stripped) > self::EVIDENCE_SNIPPET_LIMIT
            ? mb_substr($stripped, 0, self::EVIDENCE_SNIPPET_LIMIT).'…'
            : $stripped;
    }
}
