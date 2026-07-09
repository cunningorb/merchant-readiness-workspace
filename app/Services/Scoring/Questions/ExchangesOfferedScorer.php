<?php

namespace App\Services\Scoring\Questions;

use App\Contracts\QuestionScorer;

class ExchangesOfferedScorer implements QuestionScorer
{
    public function questionKey(): string
    {
        return 'exchanges.offered';
    }

    public function section(): string
    {
        return 'exchanges';
    }

    public function score(mixed $value): int
    {
        return $value === true ? 100 : 0;
    }
}
