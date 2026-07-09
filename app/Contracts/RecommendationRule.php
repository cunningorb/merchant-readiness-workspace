<?php

namespace App\Contracts;

use App\Models\Assessment;
use App\Services\Recommendations\RecommendationDraft;
use App\Services\Scoring\ScoreBreakdown;

interface RecommendationRule
{
    public function applies(Assessment $assessment, ScoreBreakdown $scores): bool;

    public function draft(Assessment $assessment, ScoreBreakdown $scores): RecommendationDraft;
}
