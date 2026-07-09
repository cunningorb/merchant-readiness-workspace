<?php

namespace Tests\Unit\Services\Scoring\Questions;

use App\Services\Scoring\Questions\CommonBottlenecksScorer;
use Tests\TestCase;

class CommonBottlenecksScorerTest extends TestCase
{
    public function test_identifies_its_question_and_section(): void
    {
        $scorer = new CommonBottlenecksScorer();

        $this->assertSame('manual_operations.common_bottlenecks', $scorer->questionKey());
        $this->assertSame('manual_operations', $scorer->section());
    }

    public function test_fewer_selected_bottlenecks_score_higher(): void
    {
        $scorer = new CommonBottlenecksScorer();

        $this->assertSame(100, $scorer->score([]));
        $this->assertSame(80, $scorer->score(['Approvals']));
        $this->assertSame(60, $scorer->score(['Approvals', 'Labels']));
        $this->assertSame(0, $scorer->score(['Approvals', 'Labels', 'Refund timing', 'Inventory updates', 'Customer support handoffs']));
    }

    public function test_non_array_value_scores_as_no_bottlenecks(): void
    {
        $scorer = new CommonBottlenecksScorer();

        $this->assertSame(100, $scorer->score(null));
    }
}
