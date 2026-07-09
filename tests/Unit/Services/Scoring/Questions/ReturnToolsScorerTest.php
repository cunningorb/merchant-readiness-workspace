<?php

namespace Tests\Unit\Services\Scoring\Questions;

use App\Services\Scoring\Questions\ReturnToolsScorer;
use Tests\TestCase;

class ReturnToolsScorerTest extends TestCase
{
    public function test_identifies_its_question_and_section(): void
    {
        $scorer = new ReturnToolsScorer();

        $this->assertSame('platform.return_tools', $scorer->questionKey());
        $this->assertSame('platform', $scorer->section());
    }

    public function test_scores_by_tooling_maturity(): void
    {
        $scorer = new ReturnToolsScorer();

        $this->assertSame(0, $scorer->score('Email/spreadsheets'));
        $this->assertSame(33, $scorer->score('Helpdesk workflow'));
        $this->assertSame(67, $scorer->score('Returns app'));
        $this->assertSame(100, $scorer->score('Custom automation'));
    }
}
