<?php

namespace Tests\Unit\Models;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentAnswerTest extends TestCase
{
    use RefreshDatabase;

    public function test_answer_belongs_to_assessment(): void
    {
        $assessment = Assessment::factory()->create();
        $answer = AssessmentAnswer::factory()->for($assessment)->create();

        $this->assertTrue($answer->assessment->is($assessment));
    }

    public function test_assessment_has_many_answers(): void
    {
        $assessment = Assessment::factory()->create();
        AssessmentAnswer::factory()->count(3)->for($assessment)->create();

        $this->assertCount(3, $assessment->fresh()->answers);
    }

    public function test_value_is_cast_to_an_array(): void
    {
        $answer = AssessmentAnswer::factory()->create([
            'value' => ['choice' => 'weekly'],
        ]);

        $this->assertSame(['choice' => 'weekly'], $answer->fresh()->value);
    }

    public function test_question_key_is_unique_per_assessment(): void
    {
        $assessment = Assessment::factory()->create();
        AssessmentAnswer::factory()->for($assessment)->create(['question_key' => 'business.order_volume']);

        $this->expectException(QueryException::class);

        AssessmentAnswer::factory()->for($assessment)->create(['question_key' => 'business.order_volume']);
    }
}
