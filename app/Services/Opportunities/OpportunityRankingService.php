<?php

namespace App\Services\Opportunities;

use App\Models\AssessmentOpportunity;
use App\Models\Recommendation;
use Illuminate\Support\Collection;

/**
 * Deterministically orders calculated opportunities and existing recommendations
 * so the report can lead with the highest-value, most-confident items first.
 */
class OpportunityRankingService
{
    private const TYPE_ORDER = [
        AssessmentOpportunity::TYPE_RETAINED_REVENUE => 0,
        AssessmentOpportunity::TYPE_MANUAL_WORK_SAVINGS => 1,
        AssessmentOpportunity::TYPE_SUPPORT_CONTACT_REDUCTION => 2,
    ];

    private const CONFIDENCE_ORDER = [
        'high' => 0,
        'medium' => 1,
        'low' => 2,
    ];

    private const PRIORITY_ORDER = [
        'high' => 0,
        'medium' => 1,
        'low' => 2,
    ];

    /**
     * @param  array<int, AssessmentOpportunity>  $opportunities
     * @return array<int, AssessmentOpportunity>
     */
    public function rankOpportunities(array $opportunities): array
    {
        $ranked = $opportunities;

        usort($ranked, function (AssessmentOpportunity $a, AssessmentOpportunity $b) {
            $typeComparison = $this->typeRank($a->type) <=> $this->typeRank($b->type);

            if ($typeComparison !== 0) {
                return $typeComparison;
            }

            return $this->midpoint($b) <=> $this->midpoint($a);
        });

        foreach ($ranked as $index => $opportunity) {
            $opportunity->sort_order = $index;
        }

        return $ranked;
    }

    /**
     * @param  Collection<int, Recommendation>  $recommendations
     * @param  array<int, AssessmentOpportunity>  $opportunities
     * @return Collection<int, Recommendation>
     */
    public function rankRecommendations(Collection $recommendations, array $opportunities): Collection
    {
        $categoryMap = config('assessment.opportunities.recommendation_category_to_opportunity_type', []);
        $opportunityByType = collect($opportunities)->keyBy(fn (AssessmentOpportunity $opportunity) => $opportunity->type);

        return $recommendations
            ->sort(function (Recommendation $a, Recommendation $b) use ($categoryMap, $opportunityByType) {
                $typeComparison = $this->recommendationTypeRank($a, $categoryMap, $opportunityByType)
                    <=> $this->recommendationTypeRank($b, $categoryMap, $opportunityByType);

                if ($typeComparison !== 0) {
                    return $typeComparison;
                }

                $confidenceComparison = $this->recommendationConfidenceRank($a, $categoryMap, $opportunityByType)
                    <=> $this->recommendationConfidenceRank($b, $categoryMap, $opportunityByType);

                if ($confidenceComparison !== 0) {
                    return $confidenceComparison;
                }

                $priorityComparison = $this->priorityRank($a) <=> $this->priorityRank($b);

                if ($priorityComparison !== 0) {
                    return $priorityComparison;
                }

                return strcmp($a->title, $b->title);
            })
            ->values();
    }

    private function typeRank(string $type): int
    {
        return self::TYPE_ORDER[$type] ?? count(self::TYPE_ORDER);
    }

    private function midpoint(AssessmentOpportunity $opportunity): float
    {
        return ((float) $opportunity->minimum_value + (float) $opportunity->maximum_value) / 2;
    }

    /**
     * @param  array<string, string>  $categoryMap
     */
    private function recommendationTypeRank(Recommendation $recommendation, array $categoryMap, Collection $opportunityByType): int
    {
        $type = $categoryMap[$recommendation->category] ?? null;

        if ($type === null) {
            return count(self::TYPE_ORDER);
        }

        $opportunity = $opportunityByType->get($type);

        return $opportunity?->sort_order ?? $this->typeRank($type);
    }

    /**
     * @param  array<string, string>  $categoryMap
     * @param  Collection<string, AssessmentOpportunity>  $opportunityByType
     */
    private function recommendationConfidenceRank(Recommendation $recommendation, array $categoryMap, Collection $opportunityByType): int
    {
        $type = $categoryMap[$recommendation->category] ?? null;
        $opportunity = $type !== null ? $opportunityByType->get($type) : null;

        if ($opportunity === null) {
            return count(self::CONFIDENCE_ORDER);
        }

        return self::CONFIDENCE_ORDER[$opportunity->confidence] ?? count(self::CONFIDENCE_ORDER);
    }

    private function priorityRank(Recommendation $recommendation): int
    {
        return self::PRIORITY_ORDER[$recommendation->priority] ?? count(self::PRIORITY_ORDER);
    }
}
