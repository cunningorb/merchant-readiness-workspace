<?php

namespace App\Services\Scoring\Questions;

use App\Contracts\QuestionScorer;

class ReturnWindowScorer implements QuestionScorer
{
    private const POINTS = [
        '14 days or less' => 0,
        '15-30 days' => 33,
        '31-60 days' => 67,
        'More than 60 days' => 100,
    ];

    public function questionKey(): string
    {
        return 'return_policy.window_days';
    }

    public function section(): string
    {
        return 'return_policy';
    }

    public function score(mixed $value): int
    {
        return self::POINTS[$value] ?? 0;
    }
}
