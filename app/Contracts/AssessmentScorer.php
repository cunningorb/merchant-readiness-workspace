<?php

namespace App\Contracts;

use App\Models\Assessment;
use App\Services\Scoring\ScoreBreakdown;

interface AssessmentScorer
{
    public function score(Assessment $assessment): ScoreBreakdown;
}
