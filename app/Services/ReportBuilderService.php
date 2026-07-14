<?php

namespace App\Services;

use App\Contracts\RecommendationInsightGenerator;
use App\Models\Assessment;
use App\Models\AssessmentBenchmarkComparison;
use App\Models\AssessmentOpportunity;
use App\Models\Recommendation;
use App\Models\Report;
use App\Services\Opportunities\OpportunityRankingService;
use Illuminate\Support\Collection;
use Throwable;

class ReportBuilderService
{
    private const PEER_COMPARISON_UNIT_LABELS = [
        'days' => 'days',
        'hours_per_week' => 'hrs/week',
        'sku_count' => 'SKUs',
    ];

    private const FORMULA_DESCRIPTIONS = [
        AssessmentOpportunity::TYPE_RETAINED_REVENUE => 'Annual order volume x estimated return rate x average order value x refund-eligible share x modeled exchange-conversion lift.',
        AssessmentOpportunity::TYPE_MANUAL_WORK_SAVINGS => 'Weekly manual returns hours x the share of that work believed automatable.',
        AssessmentOpportunity::TYPE_SUPPORT_CONTACT_REDUCTION => 'Monthly order volume x estimated return rate x policy-confusion share x modeled reduction from clearer policy communication.',
    ];

    private const SUPPORTING_METRIC_LABELS = [
        AssessmentOpportunity::TYPE_RETAINED_REVENUE => 'Retained revenue potential',
        AssessmentOpportunity::TYPE_MANUAL_WORK_SAVINGS => 'Manual work savings',
        AssessmentOpportunity::TYPE_SUPPORT_CONTACT_REDUCTION => 'Fewer support contacts',
    ];

    private const DEFAULT_TALKING_POINT = [
        'title' => 'See how automation and AI can level up your returns',
        'description' => 'Use this report as a starting point for where automation and AI can improve the returns experience for your customers and your business.',
        'expected_impact' => 'A clearer path to faster returns workflows, better customer experience, and fewer manual bottlenecks.',
    ];

    public function __construct(
        private readonly OpportunityRankingService $ranking,
        private readonly RecommendationInsightGenerator $insightGenerator,
    ) {}

    public function createForAssessment(Assessment $assessment): Report
    {
        return $assessment->report()->create([
            'published_at' => now(),
        ]);
    }

    /**
     * $includeInsight exists so callers that don't display the report (e.g.
     * the "notify sales" contact endpoint, which only needs merchant/url)
     * don't pay for LLM generation latency they'll never use.
     */
    public function buildPayload(Report $report, bool $includeInsight = true): array
    {
        $assessment = $report->assessment;
        $assessment->loadMissing(['recommendations', 'merchant', 'answers', 'opportunities', 'benchmarkComparisons']);

        $opportunities = $assessment->opportunities;
        $opportunityByType = $opportunities->keyBy('type');
        $categoryMap = config('assessment.opportunities.recommendation_category_to_opportunity_type', []);
        $rankedRecommendations = $this->ranking->rankRecommendations($assessment->recommendations, $opportunities->all());

        return [
            'token' => $report->token,
            'url' => route('reports.show', $report->token),
            'merchant' => $this->merchantProfile($assessment),
            'assessment' => [
                'overall_score' => $assessment->overall_score,
                'overall_tier' => $assessment->overall_tier,
                'section_scores' => $assessment->section_scores,
                'ranked_sections' => collect($assessment->section_scores)->sortBy('score')->all(),
            ],
            'recommendations' => $rankedRecommendations->map(fn ($recommendation) => [
                'title' => $recommendation->title,
                'description' => $recommendation->description,
                'category' => $recommendation->category,
                'priority' => $recommendation->priority,
                'expected_impact' => $recommendation->expected_impact,
            ])->all(),
            'published_at' => $report->published_at,
            'heroOpportunity' => $this->heroOpportunity($opportunities, $assessment->recommendations),
            'supportingMetrics' => $this->supportingMetrics($opportunities, $assessment),
            'topRecommendations' => $rankedRecommendations->take(3)
                ->map(fn (Recommendation $recommendation) => $this->recommendationItem($recommendation, $categoryMap, $opportunityByType))
                ->values()->all(),
            'remainingRecommendations' => $rankedRecommendations->slice(3)
                ->map(fn (Recommendation $recommendation) => $this->recommendationItem($recommendation, $categoryMap, $opportunityByType))
                ->values()->all(),
            'calculationExplanations' => $this->calculationExplanations($opportunities),
            'actionPlan' => $this->actionPlan($rankedRecommendations),
            'peerComparisons' => $this->peerComparisons($assessment->benchmarkComparisons),
            'talkingPoints' => $this->talkingPoints($rankedRecommendations),
            'aiInsight' => $includeInsight ? $this->aiInsight($assessment, $rankedRecommendations->first()) : null,
        ];
    }

    /**
     * Never lets AI-insight generation fail the report. RecommendationInsightService
     * already returns null (rather than throwing) on any provider issue; this
     * catch is defense in depth in case that contract is ever violated by a
     * future implementation.
     */
    private function aiInsight(Assessment $assessment, ?Recommendation $topRecommendation): ?array
    {
        if ($topRecommendation === null) {
            return null;
        }

        try {
            return $this->insightGenerator->generate($assessment, $topRecommendation)?->toArray();
        } catch (Throwable) {
            return null;
        }
    }

    private function merchantProfile(Assessment $assessment): array
    {
        $optional = array_filter([
            'monthly_order_volume' => $assessment->answerValue('business.monthly_order_volume'),
            'sku_count' => $assessment->answerValue('catalog.sku_count'),
            'ecommerce_platform' => $assessment->answerValue('platform.ecommerce_platform'),
        ], fn ($value) => $value !== null && $value !== '');

        return array_merge([
            'company_name' => $assessment->answerValue('business.company_name')
                ?? $assessment->merchant->company_name,
            'contact_email' => $assessment->answerValue('business.contact_email')
                ?? $assessment->merchant->contact_email,
        ], $optional);
    }

    /**
     * @param  Collection<int, AssessmentOpportunity>  $opportunities
     * @param  Collection<int, Recommendation>  $recommendations
     */
    private function heroOpportunity(Collection $opportunities, Collection $recommendations): array
    {
        $top = $opportunities->first();

        if ($top === null) {
            return $this->heroOpportunityFallback($recommendations->count());
        }

        return [
            'kind' => $top->unit === 'usd_per_year' ? 'monetary' : 'quantified',
            'type' => $top->type,
            'title' => $top->title,
            'summary' => $top->summary,
            'minimum_value' => (float) $top->minimum_value,
            'maximum_value' => (float) $top->maximum_value,
            'unit' => $top->unit,
            'confidence' => $top->confidence,
            'effort' => $top->effort,
            'assumptions' => $top->assumptions,
            'evidence' => $top->evidence,
            'formula_version' => $top->formula_version,
        ];
    }

    private function heroOpportunityFallback(int $recommendationCount): array
    {
        if ($recommendationCount === 0) {
            return [
                'kind' => 'fallback',
                'title' => 'Targeted changes are likely to improve your returns process',
                'summary' => "We don't have enough data yet to estimate specific opportunities, but reviewing your returns process is a good next step.",
                'confidence' => 'low',
            ];
        }

        $plural = $recommendationCount === 1 ? '' : 's';
        $verb = $recommendationCount === 1 ? 'is' : 'are';

        return [
            'kind' => 'fallback',
            'title' => "{$recommendationCount} operational change{$plural} {$verb} likely to improve your returns process",
            'summary' => "Based on {$recommendationCount} recommended action{$plural}, targeted changes to your returns process may improve outcomes over time.",
            'confidence' => 'low',
        ];
    }

    /**
     * @param  Collection<int, AssessmentOpportunity>  $opportunities
     */
    private function supportingMetrics(Collection $opportunities, Assessment $assessment): array
    {
        $byType = $opportunities->keyBy('type');
        $metrics = [];

        $retained = $byType->get(AssessmentOpportunity::TYPE_RETAINED_REVENUE);
        if ($retained !== null) {
            $metrics[] = $this->opportunitySupportingMetric($retained);
        }

        $manualWork = $byType->get(AssessmentOpportunity::TYPE_MANUAL_WORK_SAVINGS);
        if ($manualWork !== null) {
            $metrics[] = $this->opportunitySupportingMetric($manualWork);
        }

        if (count($metrics) < 2) {
            $supportContacts = $byType->get(AssessmentOpportunity::TYPE_SUPPORT_CONTACT_REDUCTION);
            if ($supportContacts !== null) {
                $metrics[] = $this->opportunitySupportingMetric($supportContacts);
            }
        }

        if (count($metrics) < 3) {
            $metrics[] = [
                'key' => 'overall_score',
                'label' => 'Readiness score',
                'value' => $assessment->overall_score,
                'unit' => null,
                'source' => 'score',
            ];
        }

        return array_slice($metrics, 0, 3);
    }

    private function opportunitySupportingMetric(AssessmentOpportunity $opportunity): array
    {
        return [
            'key' => $opportunity->type,
            'label' => self::SUPPORTING_METRIC_LABELS[$opportunity->type] ?? $opportunity->title,
            'value' => $this->formatRange($opportunity),
            'unit' => $opportunity->unit,
            'source' => 'opportunity',
        ];
    }

    private function formatRange(AssessmentOpportunity $opportunity): string
    {
        $minimum = (float) $opportunity->minimum_value;
        $maximum = (float) $opportunity->maximum_value;

        return match ($opportunity->unit) {
            'usd_per_year' => '$'.number_format($minimum).'-$'.number_format($maximum).' per year',
            'hours_per_week' => number_format($minimum).'-'.number_format($maximum).' hours per week',
            'contacts_per_month' => number_format($minimum).'-'.number_format($maximum).' contacts per month',
            default => number_format($minimum).'-'.number_format($maximum),
        };
    }

    /**
     * @param  array<string, string>  $categoryMap
     */
    private function recommendationItem(Recommendation $recommendation, array $categoryMap, Collection $opportunityByType): array
    {
        $type = $categoryMap[$recommendation->category] ?? null;

        return [
            'title' => $recommendation->title,
            'description' => $recommendation->description,
            'category' => $recommendation->category,
            'priority' => $recommendation->priority,
            'expected_impact' => $recommendation->expected_impact,
            'opportunity_type' => $type,
            'effort' => $type !== null ? $opportunityByType->get($type)?->effort : null,
        ];
    }

    /**
     * @param  Collection<int, AssessmentOpportunity>  $opportunities
     */
    private function calculationExplanations(Collection $opportunities): array
    {
        $explanations = [];

        foreach ($opportunities as $opportunity) {
            $explanations[$opportunity->type] = [
                'title' => $opportunity->title,
                'formula_description' => self::FORMULA_DESCRIPTIONS[$opportunity->type] ?? '',
                'inputs' => $opportunity->evidence['inputs'] ?? [],
                'assumptions' => $this->configuredAssumptions($opportunity),
                'confidence' => $opportunity->confidence,
                'formula_version' => $opportunity->formula_version,
            ];
        }

        return $explanations;
    }

    private function configuredAssumptions(AssessmentOpportunity $opportunity): array
    {
        return collect($opportunity->assumptions)
            ->filter(fn ($assumption) => is_array($assumption) && ($assumption['source'] ?? null) === 'configured_assumption')
            ->all();
    }

    /**
     * @param  Collection<int, Recommendation>  $rankedRecommendations
     */
    private function actionPlan(Collection $rankedRecommendations): array
    {
        $thisWeek = $rankedRecommendations->filter(fn (Recommendation $recommendation) => $recommendation->priority === 'high')
            ->take(3)
            ->values();

        $thisWeekIds = $thisWeek->pluck('id');

        $planNext = $rankedRecommendations
            ->reject(fn (Recommendation $recommendation) => $thisWeekIds->contains($recommendation->id))
            ->take(3)
            ->values();

        return [
            'this_week' => $thisWeek->pluck('title')->all(),
            'plan_next' => $planNext->pluck('title')->all(),
        ];
    }

    /**
     * @param  Collection<int, Recommendation>  $recommendations
     */
    private function talkingPoints(Collection $recommendations): array
    {
        return collect([self::DEFAULT_TALKING_POINT])
            ->merge($recommendations->take(3)->map(fn (Recommendation $recommendation) => [
                'title' => $recommendation->title,
                'description' => $recommendation->description,
                'expected_impact' => $recommendation->expected_impact,
            ]))
            ->values()
            ->all();
    }

    /**
     * Sourced entirely from persisted AssessmentBenchmarkComparison rows,
     * already ordered by sort_order via the assessment relation. Never
     * recalculates - an empty collection (legacy assessments, or an
     * assessment where nothing resolved) is a normal, valid state.
     *
     * Capped at 3 here defensively: "show at most 3 comparisons" is a
     * binding acceptance criterion, and this is the cheapest place to
     * enforce it regardless of how many rows end up persisted upstream.
     *
     * @param  Collection<int, AssessmentBenchmarkComparison>  $comparisons
     */
    private function peerComparisons(Collection $comparisons): array
    {
        return $comparisons->take(3)
            ->map(fn (AssessmentBenchmarkComparison $comparison) => $this->peerComparisonItem($comparison))
            ->all();
    }

    private function peerComparisonItem(AssessmentBenchmarkComparison $comparison): array
    {
        $unitLabel = self::PEER_COMPARISON_UNIT_LABELS[$comparison->unit] ?? $comparison->unit;
        $merchantValue = (float) $comparison->merchant_value;
        $minimum = (float) $comparison->minimum_value;
        $maximum = (float) $comparison->maximum_value;

        return [
            'metric_key' => $comparison->metric_key,
            'label' => $comparison->label,
            'merchant_value' => $merchantValue,
            'merchant_value_formatted' => number_format($merchantValue).' '.$unitLabel,
            'minimum_value' => $minimum,
            'maximum_value' => $maximum,
            'unit' => $comparison->unit,
            'range_formatted' => number_format($minimum).'–'.number_format($maximum).' '.$unitLabel,
            'interpretation' => $comparison->interpretation,
            'source_type' => $comparison->source_type,
            'source_label' => $comparison->source_label,
            'methodology' => $comparison->methodology,
            'benchmark_version' => $comparison->benchmark_version,
        ];
    }
}
