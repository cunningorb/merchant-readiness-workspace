<?php

namespace Tests\Unit\Services\Scoring\Questions;

use App\Services\Scoring\Questions\WeeklyHoursScorer;
use Tests\TestCase;

class WeeklyHoursScorerTest extends TestCase
{
    public function test_identifies_its_question_and_section(): void
    {
        $scorer = new WeeklyHoursScorer();

        $this->assertSame('manual_operations.weekly_hours', $scorer->questionKey());
        $this->assertSame('manual_operations', $scorer->section());
    }

    public function test_fewer_hours_score_higher(): void
    {
        $scorer = new WeeklyHoursScorer();

        $this->assertSame(0, $scorer->score('50+'));
        $this->assertSame(33, $scorer->score('21-50'));
        $this->assertSame(67, $scorer->score('5-20'));
        $this->assertSame(100, $scorer->score('Under 5'));
    }
}
