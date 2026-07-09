<?php

namespace Tests\Unit\Services\Scoring\Questions;

use App\Services\Scoring\Questions\ExchangeIncentivesScorer;
use Tests\TestCase;

class ExchangeIncentivesScorerTest extends TestCase
{
    public function test_identifies_its_question_and_section(): void
    {
        $scorer = new ExchangeIncentivesScorer();

        $this->assertSame('exchanges.incentives', $scorer->questionKey());
        $this->assertSame('exchanges', $scorer->section());
    }

    public function test_more_selected_incentives_score_higher(): void
    {
        $scorer = new ExchangeIncentivesScorer();

        $this->assertSame(0, $scorer->score([]));
        $this->assertSame(25, $scorer->score(['Bonus credit']));
        $this->assertSame(50, $scorer->score(['Bonus credit', 'Free shipping']));
        $this->assertSame(100, $scorer->score(['Bonus credit', 'Free shipping', 'Instant exchange', 'Size recommendations']));
    }

    public function test_non_array_value_scores_as_no_incentives(): void
    {
        $scorer = new ExchangeIncentivesScorer();

        $this->assertSame(0, $scorer->score(null));
    }
}
