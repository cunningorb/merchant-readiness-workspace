<?php

namespace Tests\Unit\Services\Recommendations\Rules;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Services\Recommendations\Rules\ReturnBottlenecksRule;
use App\Services\Scoring\ScoreBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnBottlenecksRuleTest extends TestCase
{
    use RefreshDatabase;

    private function emptyScores(): ScoreBreakdown
    {
        return new ScoreBreakdown(0, 'Foundational', []);
    }

    private function withBottlenecks(array $value): Assessment
    {
        $assessment = Assessment::factory()->create();
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'manual_operations.common_bottlenecks',
            'section' => 'manual_operations',
            'value' => $value,
        ]);

        return $assessment->fresh(['answers']);
    }

    public function test_applies_when_two_or_more_bottlenecks_selected(): void
    {
        $rule = new ReturnBottlenecksRule();

        $this->assertTrue($rule->applies($this->withBottlenecks(['Approvals', 'Labels']), $this->emptyScores()));
    }

    public function test_does_not_apply_when_fewer_than_two_selected(): void
    {
        $rule = new ReturnBottlenecksRule();

        $this->assertFalse($rule->applies($this->withBottlenecks(['Approvals']), $this->emptyScores()));
        $this->assertFalse($rule->applies($this->withBottlenecks([]), $this->emptyScores()));
    }

    public function test_draft_has_expected_category_and_priority(): void
    {
        $assessment = Assessment::factory()->create();
        $rule = new ReturnBottlenecksRule();

        $draft = $rule->draft($assessment, $this->emptyScores());

        $this->assertSame('manual_operations', $draft->category);
        $this->assertSame('medium', $draft->priority);
    }
}
