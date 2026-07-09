<?php

namespace App\Services\Recommendations\Rules;

use App\Contracts\RecommendationRule;
use App\Models\Assessment;
use App\Services\Recommendations\RecommendationDraft;
use App\Services\Scoring\ScoreBreakdown;

class ReturnBottlenecksRule implements RecommendationRule
{
    public function applies(Assessment $assessment, ScoreBreakdown $scores): bool
    {
        $bottlenecks = $assessment->answerValue('manual_operations.common_bottlenecks');

        return is_array($bottlenecks) && count($bottlenecks) >= 2;
    }

    public function draft(Assessment $assessment, ScoreBreakdown $scores): RecommendationDraft
    {
        return new RecommendationDraft(
            title: 'Address your top manual-operations bottlenecks',
            description: 'You reported multiple recurring bottlenecks in returns processing. Tackling the most frequent ones first, like approvals or label generation, usually gives the fastest relief.',
            category: 'manual_operations',
            priority: 'medium',
            expectedImpact: 'Reduced processing delays across the bottlenecks you flagged.',
        );
    }
}
