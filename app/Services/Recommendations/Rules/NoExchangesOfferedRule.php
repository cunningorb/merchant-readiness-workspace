<?php

namespace App\Services\Recommendations\Rules;

use App\Contracts\RecommendationRule;
use App\Models\Assessment;
use App\Services\Recommendations\RecommendationDraft;
use App\Services\Scoring\ScoreBreakdown;

class NoExchangesOfferedRule implements RecommendationRule
{
    public function applies(Assessment $assessment, ScoreBreakdown $scores): bool
    {
        return $assessment->answerValue('exchanges.offered') === false;
    }

    public function draft(Assessment $assessment, ScoreBreakdown $scores): RecommendationDraft
    {
        return new RecommendationDraft(
            title: 'Offer exchanges, not just refunds',
            description: 'You do not currently offer exchanges. Exchanges retain revenue that would otherwise be lost to a refund, and customers who just need a different size or color often prefer them.',
            category: 'exchanges',
            priority: 'high',
            expectedImpact: 'Retain revenue currently lost to refund-only returns.',
        );
    }
}
