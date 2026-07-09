<?php

namespace App\Services\Recommendations\Rules;

use App\Contracts\RecommendationRule;
use App\Models\Assessment;
use App\Services\Recommendations\RecommendationDraft;
use App\Services\Scoring\ScoreBreakdown;

class NoExchangeIncentivesRule implements RecommendationRule
{
    public function applies(Assessment $assessment, ScoreBreakdown $scores): bool
    {
        $incentives = $assessment->answerValue('exchanges.incentives');

        return $assessment->answerValue('exchanges.offered') === true
            && (is_array($incentives) ? $incentives === [] : true);
    }

    public function draft(Assessment $assessment, ScoreBreakdown $scores): RecommendationDraft
    {
        return new RecommendationDraft(
            title: 'Add incentives to encourage exchanges over refunds',
            description: 'You offer exchanges but no incentives, like free shipping or bonus credit, to nudge customers toward an exchange instead of a refund.',
            category: 'exchanges',
            priority: 'low',
            expectedImpact: 'A higher share of returns convert to exchanges instead of refunds.',
        );
    }
}
