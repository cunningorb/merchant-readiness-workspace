<?php

namespace App\Services\Recommendations\Rules;

use App\Contracts\RecommendationRule;
use App\Models\Assessment;
use App\Services\Recommendations\RecommendationDraft;
use App\Services\Scoring\ScoreBreakdown;

class BasicReturnToolingRule implements RecommendationRule
{
    public function applies(Assessment $assessment, ScoreBreakdown $scores): bool
    {
        return $assessment->answerValue('platform.return_tools') === 'Helpdesk workflow';
    }

    public function draft(Assessment $assessment, ScoreBreakdown $scores): RecommendationDraft
    {
        return new RecommendationDraft(
            title: 'Upgrade to a dedicated returns tool',
            description: 'A general helpdesk workflow works but lacks returns-specific automation, like label generation and return-reason analytics, that a dedicated returns app provides.',
            category: 'platform',
            priority: 'medium',
            expectedImpact: 'Faster return processing and better visibility into return reasons.',
        );
    }
}
