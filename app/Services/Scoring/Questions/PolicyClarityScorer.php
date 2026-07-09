<?php

namespace App\Services\Scoring\Questions;

use App\Contracts\QuestionScorer;

class PolicyClarityScorer implements QuestionScorer
{
    private const POINTS = [
        'Not documented' => 0,
        'Basic FAQ' => 33,
        'Detailed policy page' => 67,
        'Contextual policy by product/order' => 100,
    ];

    public function questionKey(): string
    {
        return 'return_policy.policy_clarity';
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
