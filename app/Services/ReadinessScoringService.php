<?php

namespace App\Services;

use App\Contracts\AssessmentScorer;
use App\Contracts\QuestionScorer;
use App\Models\Assessment;
use App\Services\Scoring\ScoreBreakdown;

class ReadinessScoringService implements AssessmentScorer
{
    /**
     * @param QuestionScorer[] $questionScorers
     */
    public function __construct(private readonly array $questionScorers)
    {
    }

    public function score(Assessment $assessment): ScoreBreakdown
    {
        $assessment->loadMissing('answers');

        $pointsBySection = [];

        foreach ($this->questionScorers as $scorer) {
            $value = $assessment->answerValue($scorer->questionKey());
            $pointsBySection[$scorer->section()][] = $scorer->score($value);
        }

        $sections = [];
        $weightedTotal = 0;
        $weightTotal = 0;

        foreach (config('scoring.section_weights') as $section => $weight) {
            $points = $pointsBySection[$section] ?? [];
            $sectionScore = $points === [] ? 0 : (int) round(array_sum($points) / count($points));

            $sections[$section] = [
                'score' => $sectionScore,
                'tier' => $this->tierFor($sectionScore),
            ];

            $weightedTotal += $sectionScore * $weight;
            $weightTotal += $weight;
        }

        $overallScore = $weightTotal === 0 ? 0 : (int) round($weightedTotal / $weightTotal);

        return new ScoreBreakdown($overallScore, $this->tierFor($overallScore), $sections);
    }

    private function tierFor(int $score): string
    {
        foreach (config('scoring.tiers') as $threshold => $label) {
            if ($score <= $threshold) {
                return $label;
            }
        }

        return 'Advanced';
    }
}
