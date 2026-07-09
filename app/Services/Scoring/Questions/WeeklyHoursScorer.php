<?php

namespace App\Services\Scoring\Questions;

use App\Contracts\QuestionScorer;

class WeeklyHoursScorer implements QuestionScorer
{
    private const POINTS = [
        '50+' => 0,
        '21-50' => 33,
        '5-20' => 67,
        'Under 5' => 100,
    ];

    public function questionKey(): string
    {
        return 'manual_operations.weekly_hours';
    }

    public function section(): string
    {
        return 'manual_operations';
    }

    public function score(mixed $value): int
    {
        return self::POINTS[$value] ?? 0;
    }
}
