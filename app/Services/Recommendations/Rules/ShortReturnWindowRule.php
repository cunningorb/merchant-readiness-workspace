<?php

namespace App\Services\Recommendations\Rules;

use App\Contracts\RecommendationRule;
use App\Models\Assessment;
use App\Services\Recommendations\RecommendationDraft;
use App\Services\Scoring\ScoreBreakdown;

class ShortReturnWindowRule implements RecommendationRule
{
    public function applies(Assessment $assessment, ScoreBreakdown $scores): bool
    {
        return $assessment->answerValue('return_policy.window_days') === '14 days or less';
    }

    public function draft(Assessment $assessment, ScoreBreakdown $scores): RecommendationDraft
    {
        return new RecommendationDraft(
            title: 'Extend your return window',
            description: 'A 14-day-or-less return window is shorter than what most merchants offer. Extending it typically increases buyer confidence and checkout conversion without meaningfully increasing return volume.',
            category: 'return_policy',
            priority: 'high',
            expectedImpact: 'Higher checkout conversion and fewer pre-purchase questions about returns.',
        );
    }
}
