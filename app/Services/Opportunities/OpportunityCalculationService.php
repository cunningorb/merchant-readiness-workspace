<?php

namespace App\Services\Opportunities;

use App\Enums\ConfidenceLevel;
use App\Enums\EffortLevel;
use App\Models\Assessment;
use App\Models\AssessmentOpportunity;

/**
 * Calculates deterministic, server-side business-opportunity estimates from an
 * assessment's banded questionnaire answers, filling gaps with configured,
 * clearly-labeled assumptions (see config/assessment.php). Never fabricates a
 * dollar value when the underlying merchant answer is missing or unrecognized.
 */
class OpportunityCalculationService
{
    /**
     * @return array<int, AssessmentOpportunity>
     */
    public function calculate(Assessment $assessment): array
    {
        $assessment->loadMissing('answers');

        $opportunities = [];

        if ($retainedRevenue = $this->calculateRetainedRevenue($assessment)) {
            $opportunities[] = $retainedRevenue;
        }

        if ($manualWorkSavings = $this->calculateManualWorkSavings($assessment)) {
            $opportunities[] = $manualWorkSavings;
        }

        if ($supportContactReduction = $this->calculateSupportContactReduction($assessment)) {
            $opportunities[] = $supportContactReduction;
        }

        return $opportunities;
    }

    private function calculateRetainedRevenue(Assessment $assessment): ?AssessmentOpportunity
    {
        $config = config('assessment.opportunities');

        $orderVolumeBand = $assessment->answerValue('business.monthly_order_volume');
        $orderVolumeMidpoint = $this->bandValue($config['order_volume_band_midpoints'], $orderVolumeBand);

        if ($orderVolumeMidpoint === null) {
            return null;
        }

        $fitSensitiveAnswered = $this->isAnswered($assessment, 'catalog.fit_sensitive_categories');
        $fitSensitiveCategories = $assessment->answerValue('catalog.fit_sensitive_categories') ?? [];
        $isFitSensitive = $this->isFitSensitive($fitSensitiveCategories);
        $estimatedReturnRate = $isFitSensitive ? $config['fit_sensitive_return_rate'] : $config['base_return_rate'];

        $exchangesOfferedAnswered = $this->isAnswered($assessment, 'exchanges.offered');
        $exchangesOffered = (bool) $assessment->answerValue('exchanges.offered');

        $annualOrders = $orderVolumeMidpoint * 12;
        $aov = $config['assumed_average_order_value'];
        $eligibleRefundShare = $config['eligible_refund_share'];

        $liftMin = $config['exchange_conversion_lift']['min'];
        $liftMax = $config['exchange_conversion_lift']['max'];

        if ($exchangesOffered) {
            $liftMin *= $config['exchange_lift_dampener_when_exchanges_offered'];
            $liftMax *= $config['exchange_lift_dampener_when_exchanges_offered'];
        }

        $rawMin = $annualOrders * $estimatedReturnRate * $aov * $eligibleRefundShare * $liftMin;
        $rawMax = $annualOrders * $estimatedReturnRate * $aov * $eligibleRefundShare * $liftMax;

        $confidence = ($fitSensitiveAnswered && $exchangesOfferedAnswered)
            ? ConfidenceLevel::Medium
            : ConfidenceLevel::Low;

        $assumptions = [
            'order_volume_band_midpoint' => ['value' => $orderVolumeMidpoint, 'source' => 'merchant_answer', 'band' => $orderVolumeBand],
            'estimated_return_rate' => ['value' => $estimatedReturnRate, 'source' => 'configured_assumption', 'based_on' => 'catalog.fit_sensitive_categories'],
            'assumed_average_order_value' => ['value' => $aov, 'source' => 'configured_assumption'],
            'eligible_refund_share' => ['value' => $eligibleRefundShare, 'source' => 'configured_assumption'],
            'exchange_conversion_lift' => [
                'value' => ['min' => $liftMin, 'max' => $liftMax],
                'source' => 'configured_assumption',
                'dampened_because_exchanges_already_offered' => $exchangesOffered,
            ],
        ];

        $evidence = new OpportunityEvidence(
            inputs: [
                'order_volume_band' => $orderVolumeBand,
                'fit_sensitive_categories' => $fitSensitiveCategories,
                'exchanges_offered' => $exchangesOffered,
            ],
            sourceAnswerKeys: ['business.monthly_order_volume', 'catalog.fit_sensitive_categories', 'exchanges.offered'],
            why: [
                "Estimated {$annualOrders} annual orders from your reported monthly order volume band ({$orderVolumeBand}).",
                $isFitSensitive
                    ? 'Used a higher, fit-sensitive return rate because Apparel or Footwear was selected as a category.'
                    : 'Used a standard return rate because no fit-sensitive category was selected.',
                'Assumed an average order value and refund-eligible share for returns (configured assumptions, not collected in the assessment).',
                $exchangesOffered
                    ? 'Halved the modeled exchange-conversion lift because you already offer exchanges.'
                    : 'Modeled the revenue that could be retained by converting more refund-eligible returns into exchanges.',
            ],
        );

        return $this->makeOpportunity(
            assessment: $assessment,
            type: AssessmentOpportunity::TYPE_RETAINED_REVENUE,
            title: 'Retained revenue from exchange conversion',
            summary: 'Estimated revenue that may be retained per year by converting more eligible returns into exchanges instead of refunds.',
            range: new MoneyRange(
                minimum: $this->roundToNearest($rawMin, 1000),
                maximum: $this->roundToNearest($rawMax, 1000),
                unit: 'usd_per_year',
            ),
            confidence: $confidence,
            assumptions: $assumptions,
            evidence: $evidence,
            formulaVersion: $config['formula_version'],
        );
    }

    private function calculateManualWorkSavings(Assessment $assessment): ?AssessmentOpportunity
    {
        $config = config('assessment.opportunities');

        $weeklyHoursBand = $assessment->answerValue('manual_operations.weekly_hours');
        $weeklyHoursMidpoint = $this->bandValue($config['weekly_manual_hours_band_midpoints'], $weeklyHoursBand);

        if ($weeklyHoursMidpoint === null) {
            return null;
        }

        $automationShareMin = $config['automation_share']['min'];
        $automationShareMax = $config['automation_share']['max'];

        $rawMin = $weeklyHoursMidpoint * $automationShareMin;
        $rawMax = $weeklyHoursMidpoint * $automationShareMax;

        $assumptions = [
            'weekly_manual_hours_band_midpoint' => ['value' => $weeklyHoursMidpoint, 'source' => 'merchant_answer', 'band' => $weeklyHoursBand],
            'automation_share' => ['value' => ['min' => $automationShareMin, 'max' => $automationShareMax], 'source' => 'configured_assumption'],
        ];

        $evidence = new OpportunityEvidence(
            inputs: [
                'weekly_manual_hours_band' => $weeklyHoursBand,
            ],
            sourceAnswerKeys: ['manual_operations.weekly_hours'],
            why: [
                "Started from your reported weekly manual returns workload ({$weeklyHoursBand}).",
                'Assumed a share of that manual work could be automated (configured assumption).',
            ],
        );

        return $this->makeOpportunity(
            assessment: $assessment,
            type: AssessmentOpportunity::TYPE_MANUAL_WORK_SAVINGS,
            title: 'Weekly manual-work savings from automation',
            summary: 'Estimated hours per week your team may save by automating manual returns handling.',
            range: new MoneyRange(
                minimum: $this->roundToNearest($rawMin, 1),
                maximum: $this->roundToNearest($rawMax, 1),
                unit: 'hours_per_week',
            ),
            confidence: ConfidenceLevel::Medium,
            assumptions: $assumptions,
            evidence: $evidence,
            formulaVersion: $config['formula_version'],
        );
    }

    private function calculateSupportContactReduction(Assessment $assessment): ?AssessmentOpportunity
    {
        $config = config('assessment.opportunities');

        $orderVolumeBand = $assessment->answerValue('business.monthly_order_volume');
        $monthlyOrders = $this->bandValue($config['order_volume_band_midpoints'], $orderVolumeBand);

        if ($monthlyOrders === null) {
            return null;
        }

        $policyClarityAnswered = $this->isAnswered($assessment, 'return_policy.policy_clarity');
        $policyClarity = $assessment->answerValue('return_policy.policy_clarity');
        $policyConfusionShare = $this->bandValue($config['policy_confusion_share'], $policyClarity);

        if ($policyConfusionShare === null) {
            return null;
        }

        $fitSensitiveCategories = $assessment->answerValue('catalog.fit_sensitive_categories') ?? [];
        $isFitSensitive = $this->isFitSensitive($fitSensitiveCategories);
        $estimatedReturnRate = $isFitSensitive ? $config['fit_sensitive_return_rate'] : $config['base_return_rate'];

        $clarityReductionMin = $config['clarity_reduction']['min'];
        $clarityReductionMax = $config['clarity_reduction']['max'];

        $rawMin = $monthlyOrders * $estimatedReturnRate * $policyConfusionShare * $clarityReductionMin;
        $rawMax = $monthlyOrders * $estimatedReturnRate * $policyConfusionShare * $clarityReductionMax;

        $orderVolumeAnswered = $this->isAnswered($assessment, 'business.monthly_order_volume');
        $confidence = ($orderVolumeAnswered && $policyClarityAnswered)
            ? ConfidenceLevel::Medium
            : ConfidenceLevel::Low;

        $assumptions = [
            'monthly_order_volume_band_midpoint' => ['value' => $monthlyOrders, 'source' => 'merchant_answer', 'band' => $orderVolumeBand],
            'estimated_return_rate' => ['value' => $estimatedReturnRate, 'source' => 'configured_assumption', 'based_on' => 'catalog.fit_sensitive_categories'],
            'policy_confusion_share' => ['value' => $policyConfusionShare, 'source' => 'configured_assumption', 'based_on' => 'return_policy.policy_clarity', 'band' => $policyClarity],
            'clarity_reduction' => ['value' => ['min' => $clarityReductionMin, 'max' => $clarityReductionMax], 'source' => 'configured_assumption'],
        ];

        $evidence = new OpportunityEvidence(
            inputs: [
                'order_volume_band' => $orderVolumeBand,
                'policy_clarity' => $policyClarity,
            ],
            sourceAnswerKeys: ['business.monthly_order_volume', 'return_policy.policy_clarity'],
            why: [
                "Started from your reported monthly order volume band ({$orderVolumeBand}).",
                "Assumed a share of returns lead to a support contact due to policy confusion, based on your reported policy clarity ({$policyClarity}).",
                'Modeled the reduction in monthly support contacts possible from clearer return policy communication (configured assumption).',
            ],
        );

        return $this->makeOpportunity(
            assessment: $assessment,
            type: AssessmentOpportunity::TYPE_SUPPORT_CONTACT_REDUCTION,
            title: 'Fewer support contacts from a clearer return policy',
            summary: 'Estimated reduction in monthly support contacts if your return policy is made clearer and easier to find.',
            range: new MoneyRange(
                minimum: $this->roundToNearest($rawMin, 1),
                maximum: $this->roundToNearest($rawMax, 1),
                unit: 'contacts_per_month',
            ),
            confidence: $confidence,
            assumptions: $assumptions,
            evidence: $evidence,
            formulaVersion: $config['formula_version'],
        );
    }

    /**
     * @param  array<string, mixed>  $assumptions
     */
    private function makeOpportunity(
        Assessment $assessment,
        string $type,
        string $title,
        string $summary,
        MoneyRange $range,
        ConfidenceLevel $confidence,
        array $assumptions,
        OpportunityEvidence $evidence,
        string $formulaVersion,
    ): AssessmentOpportunity {
        return new AssessmentOpportunity([
            'assessment_id' => $assessment->id,
            'type' => $type,
            'title' => $title,
            'summary' => $summary,
            'minimum_value' => $range->minimum,
            'maximum_value' => $range->maximum,
            'unit' => $range->unit,
            'confidence' => $confidence->value,
            'effort' => $this->effortFor($type)->value,
            'assumptions' => $assumptions,
            'evidence' => $evidence->toArray(),
            'formula_version' => $formulaVersion,
            'sort_order' => 0,
        ]);
    }

    private function effortFor(string $type): EffortLevel
    {
        $effort = config("assessment.opportunities.effort_by_type.{$type}");

        return EffortLevel::from($effort);
    }

    /**
     * @param  array<int, string>  $fitSensitiveCategories
     */
    private function isFitSensitive(array $fitSensitiveCategories): bool
    {
        return array_intersect($fitSensitiveCategories, ['Apparel', 'Footwear']) !== [];
    }

    /**
     * @param  array<string, mixed>  $map
     */
    private function bandValue(array $map, mixed $band): mixed
    {
        if (! is_string($band)) {
            return null;
        }

        return $map[$band] ?? null;
    }

    private function isAnswered(Assessment $assessment, string $questionKey): bool
    {
        return $assessment->answers->contains('question_key', $questionKey);
    }

    private function roundToNearest(float $value, int $nearest): float
    {
        return round($value / $nearest) * $nearest;
    }
}
