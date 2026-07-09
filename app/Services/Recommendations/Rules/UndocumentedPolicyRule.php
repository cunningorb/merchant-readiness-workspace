<?php

namespace App\Services\Recommendations\Rules;

use App\Contracts\RecommendationRule;
use App\Models\Assessment;
use App\Services\Recommendations\RecommendationDraft;
use App\Services\Scoring\ScoreBreakdown;

class UndocumentedPolicyRule implements RecommendationRule
{
    private const WEAK_CLARITY = ['Not documented', 'Basic FAQ'];

    public function applies(Assessment $assessment, ScoreBreakdown $scores): bool
    {
        return in_array($assessment->answerValue('return_policy.policy_clarity'), self::WEAK_CLARITY, true);
    }

    public function draft(Assessment $assessment, ScoreBreakdown $scores): RecommendationDraft
    {
        return new RecommendationDraft(
            title: 'Publish a clearer return policy page',
            description: 'Your return policy is not documented in detail. A dedicated, easy-to-find policy page reduces support tickets and pre-purchase hesitation.',
            category: 'return_policy',
            priority: 'medium',
            expectedImpact: 'Fewer "what is your return policy" support tickets and clearer expectations at checkout.',
        );
    }
}
