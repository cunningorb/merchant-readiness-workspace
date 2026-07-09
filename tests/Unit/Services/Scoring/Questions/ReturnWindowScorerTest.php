<?php

namespace Tests\Unit\Services\Scoring\Questions;

use App\Services\Scoring\Questions\ReturnWindowScorer;
use Tests\TestCase;

class ReturnWindowScorerTest extends TestCase
{
    public function test_identifies_its_question_and_section(): void
    {
        $scorer = new ReturnWindowScorer();

        $this->assertSame('return_policy.window_days', $scorer->questionKey());
        $this->assertSame('return_policy', $scorer->section());
    }

    public function test_scores_by_window_length(): void
    {
        $scorer = new ReturnWindowScorer();

        $this->assertSame(0, $scorer->score('14 days or less'));
        $this->assertSame(33, $scorer->score('15-30 days'));
        $this->assertSame(67, $scorer->score('31-60 days'));
        $this->assertSame(100, $scorer->score('More than 60 days'));
    }
}
