<?php

namespace Tests\Unit\Services\Opportunities;

use App\Enums\ConfidenceLevel;
use App\Enums\EffortLevel;
use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Models\AssessmentOpportunity;
use App\Services\Opportunities\OpportunityCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class OpportunityCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function answer(Assessment $assessment, string $questionKey, string $section, mixed $value): void
    {
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => $questionKey,
            'section' => $section,
            'value' => $value,
        ]);
    }

    /**
     * @return Collection<int, AssessmentOpportunity>
     */
    private function calculate(Assessment $assessment): Collection
    {
        return collect((new OpportunityCalculationService)->calculate($assessment->fresh(['answers'])));
    }

    private function byType(Collection $opportunities, string $type): ?AssessmentOpportunity
    {
        return $opportunities->firstWhere('type', $type);
    }

    // --- retained_revenue -------------------------------------------------

    public function test_retained_revenue_hand_computed_for_fit_sensitive_category(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'business.monthly_order_volume', 'business', '1,000-10,000');
        $this->answer($assessment, 'catalog.fit_sensitive_categories', 'catalog', ['Apparel']);
        $this->answer($assessment, 'exchanges.offered', 'exchanges', false);

        $opportunity = $this->byType($this->calculate($assessment), AssessmentOpportunity::TYPE_RETAINED_REVENUE);

        $this->assertNotNull($opportunity);
        $this->assertSame('usd_per_year', $opportunity->unit);
        $this->assertEqualsWithDelta(46000.0, (float) $opportunity->minimum_value, 0.001);
        $this->assertEqualsWithDelta(69000.0, (float) $opportunity->maximum_value, 0.001);
        $this->assertSame(ConfidenceLevel::Medium->value, $opportunity->confidence);
        $this->assertSame(EffortLevel::Medium->value, $opportunity->effort);
        $this->assertSame('1.0', $opportunity->formula_version);
        $this->assertStringNotContainsStringIgnoringCase('profit', $opportunity->title);
        $this->assertStringNotContainsStringIgnoringCase('profit', $opportunity->summary);
    }

    public function test_retained_revenue_uses_base_rate_when_not_fit_sensitive(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'business.monthly_order_volume', 'business', '1,000-10,000');
        $this->answer($assessment, 'catalog.fit_sensitive_categories', 'catalog', []);
        $this->answer($assessment, 'exchanges.offered', 'exchanges', false);

        $opportunity = $this->byType($this->calculate($assessment), AssessmentOpportunity::TYPE_RETAINED_REVENUE);

        $this->assertEqualsWithDelta(22000.0, (float) $opportunity->minimum_value, 0.001);
        $this->assertEqualsWithDelta(32000.0, (float) $opportunity->maximum_value, 0.001);
    }

    public function test_retained_revenue_exchange_lift_is_halved_when_exchanges_already_offered(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'business.monthly_order_volume', 'business', '1,000-10,000');
        $this->answer($assessment, 'catalog.fit_sensitive_categories', 'catalog', []);
        $this->answer($assessment, 'exchanges.offered', 'exchanges', true);

        $opportunity = $this->byType($this->calculate($assessment), AssessmentOpportunity::TYPE_RETAINED_REVENUE);

        $this->assertEqualsWithDelta(11000.0, (float) $opportunity->minimum_value, 0.001);
        $this->assertEqualsWithDelta(16000.0, (float) $opportunity->maximum_value, 0.001);
    }

    public function test_retained_revenue_and_support_contacts_skipped_when_order_volume_unanswered(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'manual_operations.weekly_hours', 'manual_operations', '21-50');
        $this->answer($assessment, 'return_policy.policy_clarity', 'return_policy', 'Basic FAQ');

        $opportunities = $this->calculate($assessment);

        $this->assertNull($this->byType($opportunities, AssessmentOpportunity::TYPE_RETAINED_REVENUE));
        $this->assertNull($this->byType($opportunities, AssessmentOpportunity::TYPE_SUPPORT_CONTACT_REDUCTION));
        $this->assertNotNull($this->byType($opportunities, AssessmentOpportunity::TYPE_MANUAL_WORK_SAVINGS));
    }

    public function test_retained_revenue_and_support_contacts_skipped_when_order_volume_band_unknown(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'business.monthly_order_volume', 'business', 'Not a real band');
        $this->answer($assessment, 'return_policy.policy_clarity', 'return_policy', 'Basic FAQ');

        $opportunities = $this->calculate($assessment);

        $this->assertNull($this->byType($opportunities, AssessmentOpportunity::TYPE_RETAINED_REVENUE));
        $this->assertNull($this->byType($opportunities, AssessmentOpportunity::TYPE_SUPPORT_CONTACT_REDUCTION));
    }

    // --- manual_work_savings ----------------------------------------------

    public function test_manual_work_savings_hand_computed_range(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'manual_operations.weekly_hours', 'manual_operations', '21-50');

        $opportunity = $this->byType($this->calculate($assessment), AssessmentOpportunity::TYPE_MANUAL_WORK_SAVINGS);

        $this->assertNotNull($opportunity);
        $this->assertSame('hours_per_week', $opportunity->unit);
        $this->assertEqualsWithDelta(14.0, (float) $opportunity->minimum_value, 0.001);
        $this->assertEqualsWithDelta(21.0, (float) $opportunity->maximum_value, 0.001);
        $this->assertSame(ConfidenceLevel::Medium->value, $opportunity->confidence);
        $this->assertSame(EffortLevel::Medium->value, $opportunity->effort);
    }

    public function test_manual_work_savings_rounds_small_band_correctly(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'manual_operations.weekly_hours', 'manual_operations', 'Under 5');

        $opportunity = $this->byType($this->calculate($assessment), AssessmentOpportunity::TYPE_MANUAL_WORK_SAVINGS);

        $this->assertEqualsWithDelta(1.0, (float) $opportunity->minimum_value, 0.001);
        $this->assertEqualsWithDelta(2.0, (float) $opportunity->maximum_value, 0.001);
    }

    public function test_manual_work_savings_skipped_when_weekly_hours_missing(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'business.monthly_order_volume', 'business', '1,000-10,000');

        $opportunities = $this->calculate($assessment);

        $this->assertNull($this->byType($opportunities, AssessmentOpportunity::TYPE_MANUAL_WORK_SAVINGS));
    }

    public function test_manual_work_savings_skipped_when_weekly_hours_band_unknown(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'manual_operations.weekly_hours', 'manual_operations', 'Not a real band');

        $opportunities = $this->calculate($assessment);

        $this->assertNull($this->byType($opportunities, AssessmentOpportunity::TYPE_MANUAL_WORK_SAVINGS));
    }

    // --- support_contact_reduction ------------------------------------------

    public function test_support_contact_reduction_hand_computed_range(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'business.monthly_order_volume', 'business', '10,001-50,000');
        $this->answer($assessment, 'catalog.fit_sensitive_categories', 'catalog', ['Footwear']);
        $this->answer($assessment, 'return_policy.policy_clarity', 'return_policy', 'Basic FAQ');

        $opportunity = $this->byType($this->calculate($assessment), AssessmentOpportunity::TYPE_SUPPORT_CONTACT_REDUCTION);

        $this->assertNotNull($opportunity);
        $this->assertSame('contacts_per_month', $opportunity->unit);
        $this->assertEqualsWithDelta(383.0, (float) $opportunity->minimum_value, 0.001);
        $this->assertEqualsWithDelta(638.0, (float) $opportunity->maximum_value, 0.001);
        $this->assertSame(ConfidenceLevel::Medium->value, $opportunity->confidence);
        $this->assertSame(EffortLevel::Low->value, $opportunity->effort);
    }

    public function test_support_contact_reduction_skipped_when_policy_clarity_missing(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'business.monthly_order_volume', 'business', '10,001-50,000');

        $opportunities = $this->calculate($assessment);

        $this->assertNull($this->byType($opportunities, AssessmentOpportunity::TYPE_SUPPORT_CONTACT_REDUCTION));
    }

    public function test_support_contact_reduction_skipped_when_policy_clarity_band_unknown(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'business.monthly_order_volume', 'business', '10,001-50,000');
        $this->answer($assessment, 'return_policy.policy_clarity', 'return_policy', 'Not a real band');

        $opportunities = $this->calculate($assessment);

        $this->assertNull($this->byType($opportunities, AssessmentOpportunity::TYPE_SUPPORT_CONTACT_REDUCTION));
    }

    // --- confidence ---------------------------------------------------------

    public function test_retained_revenue_confidence_is_low_when_fit_sensitive_categories_unanswered(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'business.monthly_order_volume', 'business', '1,000-10,000');
        $this->answer($assessment, 'exchanges.offered', 'exchanges', false);

        $opportunity = $this->byType($this->calculate($assessment), AssessmentOpportunity::TYPE_RETAINED_REVENUE);

        $this->assertSame(ConfidenceLevel::Low->value, $opportunity->confidence);
    }

    public function test_retained_revenue_confidence_is_low_when_exchanges_offered_unanswered(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'business.monthly_order_volume', 'business', '1,000-10,000');
        $this->answer($assessment, 'catalog.fit_sensitive_categories', 'catalog', ['Apparel']);

        $opportunity = $this->byType($this->calculate($assessment), AssessmentOpportunity::TYPE_RETAINED_REVENUE);

        $this->assertSame(ConfidenceLevel::Low->value, $opportunity->confidence);
    }

    public function test_retained_revenue_confidence_is_medium_when_fit_sensitive_categories_answered_empty(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'business.monthly_order_volume', 'business', '1,000-10,000');
        $this->answer($assessment, 'catalog.fit_sensitive_categories', 'catalog', []);
        $this->answer($assessment, 'exchanges.offered', 'exchanges', false);

        $opportunity = $this->byType($this->calculate($assessment), AssessmentOpportunity::TYPE_RETAINED_REVENUE);

        $this->assertSame(ConfidenceLevel::Medium->value, $opportunity->confidence);
    }

    public function test_confidence_is_never_high(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'business.monthly_order_volume', 'business', '10,001-50,000');
        $this->answer($assessment, 'catalog.fit_sensitive_categories', 'catalog', ['Apparel']);
        $this->answer($assessment, 'exchanges.offered', 'exchanges', false);
        $this->answer($assessment, 'manual_operations.weekly_hours', 'manual_operations', '21-50');
        $this->answer($assessment, 'return_policy.policy_clarity', 'return_policy', 'Basic FAQ');

        $opportunities = $this->calculate($assessment);

        $this->assertCount(3, $opportunities);
        $this->assertFalse($opportunities->contains(fn (AssessmentOpportunity $o) => $o->confidence === ConfidenceLevel::High->value));
    }

    // --- config override ------------------------------------------------------

    public function test_changing_config_value_changes_calculated_range(): void
    {
        config()->set('assessment.opportunities.assumed_average_order_value', 100.0);

        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'business.monthly_order_volume', 'business', '1,000-10,000');
        $this->answer($assessment, 'catalog.fit_sensitive_categories', 'catalog', ['Apparel']);
        $this->answer($assessment, 'exchanges.offered', 'exchanges', false);

        $opportunity = $this->byType($this->calculate($assessment), AssessmentOpportunity::TYPE_RETAINED_REVENUE);

        $this->assertEqualsWithDelta(54000.0, (float) $opportunity->minimum_value, 0.001);
        $this->assertEqualsWithDelta(81000.0, (float) $opportunity->maximum_value, 0.001);
    }

    // --- evidence / assumptions -------------------------------------------

    public function test_retained_revenue_evidence_records_source_answer_keys(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'business.monthly_order_volume', 'business', '1,000-10,000');
        $this->answer($assessment, 'catalog.fit_sensitive_categories', 'catalog', ['Apparel']);
        $this->answer($assessment, 'exchanges.offered', 'exchanges', false);

        $opportunity = $this->byType($this->calculate($assessment), AssessmentOpportunity::TYPE_RETAINED_REVENUE);

        $this->assertNotEmpty($opportunity->assumptions);
        $this->assertEqualsCanonicalizing(
            ['business.monthly_order_volume', 'catalog.fit_sensitive_categories', 'exchanges.offered'],
            $opportunity->evidence['source_answer_keys'],
        );
        $this->assertNotEmpty($opportunity->evidence['why']);
    }
}
