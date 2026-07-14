<?php

namespace Tests\Unit\Services\Opportunities;

use App\Models\AssessmentOpportunity;
use App\Models\Recommendation;
use App\Services\Opportunities\OpportunityRankingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpportunityRankingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function opportunity(string $type, float $min, float $max, string $confidence = 'medium', ?int $sortOrder = null): AssessmentOpportunity
    {
        return AssessmentOpportunity::factory()->make([
            'type' => $type,
            'minimum_value' => $min,
            'maximum_value' => $max,
            'confidence' => $confidence,
            'sort_order' => $sortOrder,
        ]);
    }

    // --- rankOpportunities ---------------------------------------------------

    public function test_orders_by_type_then_midpoint_descending_and_assigns_sort_order(): void
    {
        $support = $this->opportunity(AssessmentOpportunity::TYPE_SUPPORT_CONTACT_REDUCTION, 10, 20);
        $manual = $this->opportunity(AssessmentOpportunity::TYPE_MANUAL_WORK_SAVINGS, 5, 10);
        $revenue = $this->opportunity(AssessmentOpportunity::TYPE_RETAINED_REVENUE, 1000, 2000);

        $ranked = (new OpportunityRankingService)->rankOpportunities([$support, $manual, $revenue]);

        $this->assertSame([
            AssessmentOpportunity::TYPE_RETAINED_REVENUE,
            AssessmentOpportunity::TYPE_MANUAL_WORK_SAVINGS,
            AssessmentOpportunity::TYPE_SUPPORT_CONTACT_REDUCTION,
        ], array_map(fn (AssessmentOpportunity $o) => $o->type, $ranked));

        $this->assertSame([0, 1, 2], array_map(fn (AssessmentOpportunity $o) => $o->sort_order, $ranked));
    }

    public function test_orders_same_type_by_midpoint_descending(): void
    {
        $lower = $this->opportunity(AssessmentOpportunity::TYPE_RETAINED_REVENUE, 1000, 2000); // midpoint 1500
        $higher = $this->opportunity(AssessmentOpportunity::TYPE_RETAINED_REVENUE, 5000, 9000); // midpoint 7000

        $ranked = (new OpportunityRankingService)->rankOpportunities([$lower, $higher]);

        $this->assertSame($higher, $ranked[0]);
        $this->assertSame($lower, $ranked[1]);
        $this->assertSame(0, $ranked[0]->sort_order);
        $this->assertSame(1, $ranked[1]->sort_order);
    }

    // --- rankRecommendations --------------------------------------------------

    private function recommendation(string $category, string $priority, string $title): Recommendation
    {
        return Recommendation::factory()->make([
            'category' => $category,
            'priority' => $priority,
            'title' => $title,
        ]);
    }

    public function test_ranks_recommendations_by_linked_opportunity_type_order(): void
    {
        $opportunities = [
            $this->opportunity(AssessmentOpportunity::TYPE_RETAINED_REVENUE, 1000, 2000),
            $this->opportunity(AssessmentOpportunity::TYPE_MANUAL_WORK_SAVINGS, 5, 10),
            $this->opportunity(AssessmentOpportunity::TYPE_SUPPORT_CONTACT_REDUCTION, 10, 20),
        ];

        $manualOpsRec = $this->recommendation('manual_operations', 'low', 'Automate approvals');
        $exchangesRec = $this->recommendation('exchanges', 'low', 'Offer exchanges');
        $policyRec = $this->recommendation('return_policy', 'low', 'Clarify policy');

        $ranked = (new OpportunityRankingService)->rankRecommendations(
            collect([$manualOpsRec, $policyRec, $exchangesRec]),
            $opportunities,
        );

        $this->assertSame(
            ['Offer exchanges', 'Automate approvals', 'Clarify policy'],
            $ranked->pluck('title')->all(),
        );
    }

    public function test_ranks_recommendations_by_persisted_opportunity_sort_order_when_available(): void
    {
        $opportunities = [
            $this->opportunity(AssessmentOpportunity::TYPE_RETAINED_REVENUE, 1000, 2000, sortOrder: 2),
            $this->opportunity(AssessmentOpportunity::TYPE_MANUAL_WORK_SAVINGS, 5, 10, sortOrder: 0),
            $this->opportunity(AssessmentOpportunity::TYPE_SUPPORT_CONTACT_REDUCTION, 10, 20, sortOrder: 1),
        ];

        $manualOpsRec = $this->recommendation('manual_operations', 'low', 'Automate approvals');
        $exchangesRec = $this->recommendation('exchanges', 'low', 'Offer exchanges');
        $policyRec = $this->recommendation('return_policy', 'low', 'Clarify policy');

        $ranked = (new OpportunityRankingService)->rankRecommendations(
            collect([$exchangesRec, $policyRec, $manualOpsRec]),
            $opportunities,
        );

        $this->assertSame(
            ['Automate approvals', 'Clarify policy', 'Offer exchanges'],
            $ranked->pluck('title')->all(),
        );
    }

    public function test_unmapped_category_ranks_after_mapped_categories(): void
    {
        $opportunities = [
            $this->opportunity(AssessmentOpportunity::TYPE_SUPPORT_CONTACT_REDUCTION, 10, 20),
        ];

        $platformRec = $this->recommendation('platform', 'high', 'Adopt returns app');
        $policyRec = $this->recommendation('return_policy', 'low', 'Clarify policy');

        $ranked = (new OpportunityRankingService)->rankRecommendations(
            collect([$platformRec, $policyRec]),
            $opportunities,
        );

        $this->assertSame(['Clarify policy', 'Adopt returns app'], $ranked->pluck('title')->all());
    }

    public function test_recommendation_still_ranked_by_type_when_linked_opportunity_missing_from_array(): void
    {
        // The support_contact_reduction opportunity was skipped during calculation (e.g. missing
        // input), so it is absent from the $opportunities array entirely. The recommendation must
        // still be ranked by its category's type order rather than erroring or dropping to the end.
        $manualOpportunity = $this->opportunity(AssessmentOpportunity::TYPE_MANUAL_WORK_SAVINGS, 5, 10, 'medium');

        $manualOpsRec = $this->recommendation('manual_operations', 'low', 'Automate approvals');
        $policyRec = $this->recommendation('return_policy', 'low', 'Clarify policy');

        $ranked = (new OpportunityRankingService)->rankRecommendations(
            collect([$policyRec, $manualOpsRec]),
            [$manualOpportunity],
        );

        // manual_operations (type rank 1) still sorts before return_policy (type rank 2)
        // even though the support_contact_reduction opportunity never made it into the array.
        $this->assertSame(['Automate approvals', 'Clarify policy'], $ranked->pluck('title')->all());
    }

    public function test_ranks_by_priority_then_title_when_type_and_confidence_tie(): void
    {
        $opportunity = $this->opportunity(AssessmentOpportunity::TYPE_MANUAL_WORK_SAVINGS, 5, 10, 'medium');

        $lowPriorityB = $this->recommendation('manual_operations', 'low', 'B recommendation');
        $highPriority = $this->recommendation('manual_operations', 'high', 'Z recommendation');
        $lowPriorityA = $this->recommendation('manual_operations', 'low', 'A recommendation');

        $ranked = (new OpportunityRankingService)->rankRecommendations(
            collect([$lowPriorityB, $highPriority, $lowPriorityA]),
            [$opportunity],
        );

        $this->assertSame(
            ['Z recommendation', 'A recommendation', 'B recommendation'],
            $ranked->pluck('title')->all(),
        );
    }
}
