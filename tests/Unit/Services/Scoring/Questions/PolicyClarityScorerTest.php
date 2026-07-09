<?php

namespace Tests\Unit\Services\Scoring\Questions;

use App\Services\Scoring\Questions\PolicyClarityScorer;
use Tests\TestCase;

class PolicyClarityScorerTest extends TestCase
{
    public function test_identifies_its_question_and_section(): void
    {
        $scorer = new PolicyClarityScorer();

        $this->assertSame('return_policy.policy_clarity', $scorer->questionKey());
        $this->assertSame('return_policy', $scorer->section());
    }

    public function test_scores_by_clarity_level(): void
    {
        $scorer = new PolicyClarityScorer();

        $this->assertSame(0, $scorer->score('Not documented'));
        $this->assertSame(33, $scorer->score('Basic FAQ'));
        $this->assertSame(67, $scorer->score('Detailed policy page'));
        $this->assertSame(100, $scorer->score('Contextual policy by product/order'));
    }
}
