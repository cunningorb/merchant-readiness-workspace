<?php

namespace App\Contracts;

interface QuestionScorer
{
    public function questionKey(): string;

    public function section(): string;

    public function score(mixed $value): int;
}
