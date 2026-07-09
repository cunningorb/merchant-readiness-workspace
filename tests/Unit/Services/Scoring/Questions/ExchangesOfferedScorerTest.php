<?php

namespace Tests\Unit\Services\Scoring\Questions;

use App\Services\Scoring\Questions\ExchangesOfferedScorer;
use Tests\TestCase;

class ExchangesOfferedScorerTest extends TestCase
{
    public function test_identifies_its_question_and_section(): void
    {
        $scorer = new ExchangesOfferedScorer();

        $this->assertSame('exchanges.offered', $scorer->questionKey());
        $this->assertSame('exchanges', $scorer->section());
    }

    public function test_scores_boolean_answer(): void
    {
        $scorer = new ExchangesOfferedScorer();

        $this->assertSame(100, $scorer->score(true));
        $this->assertSame(0, $scorer->score(false));
    }
}
