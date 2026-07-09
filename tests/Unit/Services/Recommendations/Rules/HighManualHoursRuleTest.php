<?php

namespace Tests\Unit\Services\Recommendations\Rules;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Services\Recommendations\Rules\HighManualHoursRule;
use App\Services\Scoring\ScoreBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HighManualHoursRuleTest extends TestCase
{
    use RefreshDatabase;

    private function emptyScores(): ScoreBreakdown
    {
        return new ScoreBreakdown(0, 'Foundational', []);
    }

    private function withHours(string $value): Assessment
    {
        $assessment = Assessment::factory()->create();
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'manual_operations.weekly_hours',
            'section' => 'manual_operations',
            'value' => $value,
        ]);

        return $assessment->fresh(['answers']);
    }

    public function test_applies_when_hours_are_21_to_50(): void
    {
        $rule = new HighManualHoursRule();

        $this->assertTrue($rule->applies($this->withHours('21-50'), $this->emptyScores()));
    }

    public function test_applies_when_hours_are_50_plus(): void
    {
        $rule = new HighManualHoursRule();

        $this->assertTrue($rule->applies($this->withHours('50+'), $this->emptyScores()));
    }

    public function test_does_not_apply_when_hours_are_low(): void
    {
        $rule = new HighManualHoursRule();

        $this->assertFalse($rule->applies($this->withHours('Under 5'), $this->emptyScores()));
    }

    public function test_draft_has_expected_category_and_priority(): void
    {
        $assessment = Assessment::factory()->create();
        $rule = new HighManualHoursRule();

        $draft = $rule->draft($assessment, $this->emptyScores());

        $this->assertSame('manual_operations', $draft->category);
        $this->assertSame('high', $draft->priority);
    }
}
