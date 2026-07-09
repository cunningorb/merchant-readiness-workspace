<?php

namespace App\Services\Recommendations\Rules;

use App\Contracts\RecommendationRule;
use App\Models\Assessment;
use App\Services\Recommendations\RecommendationDraft;
use App\Services\Scoring\ScoreBreakdown;

class ManualReturnToolingRule implements RecommendationRule
{
    public function applies(Assessment $assessment, ScoreBreakdown $scores): bool
    {
        return $assessment->answerValue('platform.return_tools') === 'Email/spreadsheets';
    }

    public function draft(Assessment $assessment, ScoreBreakdown $scores): RecommendationDraft
    {
        return new RecommendationDraft(
            title: 'Move off email and spreadsheets for returns',
            description: 'Managing returns through email and spreadsheets does not scale and is error-prone. A dedicated returns app or automation gives you tracking, reporting, and fewer manual mistakes.',
            category: 'platform',
            priority: 'high',
            expectedImpact: 'Fewer manual errors and real visibility into return volume and reasons.',
        );
    }
}
