<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentOpportunity;
use App\Models\Recommendation;
use App\Models\Report;
use App\Services\Opportunities\OpportunityRankingService;
use Illuminate\Support\Collection;

class ReportBuilderService
{
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

    public function __construct(
        private readonly OpportunityRankingService $ranking,
    ) {}

    public function createForAssessment(Assessment $assessment): Report
    {
        return $assessment->report()->create([
            'published_at' => now(),
        ]);
    }

    public function buildPayload(Report $report): array
    {
        $assessment = $report->assessment;
        $assessment->loadMissing(['recommendations', 'merchant', 'answers', 'opportunities']);

        $opportunities = $assessment->opportunities;
        $categoryMap = config('assessment.opportunities.recommendation_category_to_opportunity_type', []);
        $rankedRecommendations = $this->ranking->rankRecommendations($assessment->recommendations, $opportunities->all());

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
            'heroOpportunity' => $this->heroOpportunity($opportunities, $assessment->recommendations),
            'supportingMetrics' => $this->supportingMetrics($opportunities, $assessment),
            'topRecommendations' => $rankedRecommendations->take(3)
                ->map(fn (Recommendation $recommendation) => $this->recommendationItem($recommendation, $categoryMap))
                ->values()->all(),
            'remainingRecommendations' => $rankedRecommendations->slice(3)
                ->map(fn (Recommendation $recommendation) => $this->recommendationItem($recommendation, $categoryMap))
                ->values()->all(),
            'calculationExplanations' => $this->calculationExplanations($opportunities),
            'actionPlan' => $this->actionPlan($rankedRecommendations),
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
    private function recommendationItem(Recommendation $recommendation, array $categoryMap): array
    {
        return [
            'title' => $recommendation->title,
            'description' => $recommendation->description,
            'category' => $recommendation->category,
            'priority' => $recommendation->priority,
            'expected_impact' => $recommendation->expected_impact,
            'opportunity_type' => $categoryMap[$recommendation->category] ?? null,
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
}
