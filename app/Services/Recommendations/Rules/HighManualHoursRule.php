<?php

namespace App\Services\Recommendations\Rules;

use App\Contracts\RecommendationRule;
use App\Models\Assessment;
use App\Services\Recommendations\RecommendationDraft;
use App\Services\Scoring\ScoreBreakdown;

class HighManualHoursRule implements RecommendationRule
{
    private const HIGH_HOURS = ['21-50', '50+'];

    public function applies(Assessment $assessment, ScoreBreakdown $scores): bool
    {
        return in_array($assessment->answerValue('manual_operations.weekly_hours'), self::HIGH_HOURS, true);
    }

    public function draft(Assessment $assessment, ScoreBreakdown $scores): RecommendationDraft
    {
        return new RecommendationDraft(
            title: 'Reduce manual returns processing time',
            description: 'Your team spends significant weekly hours manually processing returns. Automating approvals and label generation typically frees up meaningful staff time.',
            category: 'manual_operations',
            priority: 'high',
            expectedImpact: 'Fewer staff-hours spent per week on manual returns processing.',
        );
    }
}
