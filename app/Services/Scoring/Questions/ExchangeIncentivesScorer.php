<?php

namespace App\Services\Scoring\Questions;

use App\Contracts\QuestionScorer;

class ExchangeIncentivesScorer implements QuestionScorer
{
    private const TOTAL_OPTIONS = 4;

    public function questionKey(): string
    {
        return 'exchanges.incentives';
    }

    public function section(): string
    {
        return 'exchanges';
    }

    public function score(mixed $value): int
    {
        $selected = is_array($value) ? count($value) : 0;

        return (int) round(($selected / self::TOTAL_OPTIONS) * 100);
    }
}
