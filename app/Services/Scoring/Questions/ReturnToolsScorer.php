<?php

namespace App\Services\Scoring\Questions;

use App\Contracts\QuestionScorer;

class ReturnToolsScorer implements QuestionScorer
{
    private const POINTS = [
        'Email/spreadsheets' => 0,
        'Helpdesk workflow' => 33,
        'Returns app' => 67,
        'Custom automation' => 100,
    ];

    public function questionKey(): string
    {
        return 'platform.return_tools';
    }

    public function section(): string
    {
        return 'platform';
    }

    public function score(mixed $value): int
    {
        return self::POINTS[$value] ?? 0;
    }
}
