<?php

namespace App\Services\Scoring\Questions;

use App\Contracts\QuestionScorer;

class CommonBottlenecksScorer implements QuestionScorer
{
    private const TOTAL_OPTIONS = 5;

    public function questionKey(): string
    {
        return 'manual_operations.common_bottlenecks';
    }

    public function section(): string
    {
        return 'manual_operations';
    }

    public function score(mixed $value): int
    {
        $selected = is_array($value) ? count($value) : 0;

        return (int) round(100 - ($selected / self::TOTAL_OPTIONS) * 100);
    }
}
