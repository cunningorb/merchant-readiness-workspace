<?php

namespace Tests\Unit\Services\Recommendations\Rules;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Services\Recommendations\Rules\ShortReturnWindowRule;
use App\Services\Scoring\ScoreBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShortReturnWindowRuleTest extends TestCase
{
    use RefreshDatabase;

    private function emptyScores(): ScoreBreakdown
    {
        return new ScoreBreakdown(0, 'Foundational', []);
    }

    public function test_applies_when_window_is_14_days_or_less(): void
    {
        $assessment = Assessment::factory()->create();
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'return_policy.window_days',
            'section' => 'return_policy',
            'value' => '14 days or less',
        ]);

        $rule = new ShortReturnWindowRule();

        $this->assertTrue($rule->applies($assessment->fresh(['answers']), $this->emptyScores()));
    }

    public function test_does_not_apply_when_window_is_longer(): void
    {
        $assessment = Assessment::factory()->create();
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'return_policy.window_days',
            'section' => 'return_policy',
            'value' => '31-60 days',
        ]);

        $rule = new ShortReturnWindowRule();

        $this->assertFalse($rule->applies($assessment->fresh(['answers']), $this->emptyScores()));
    }

    public function test_draft_has_expected_category_and_priority(): void
    {
        $assessment = Assessment::factory()->create();
        $rule = new ShortReturnWindowRule();

        $draft = $rule->draft($assessment, $this->emptyScores());

        $this->assertSame('return_policy', $draft->category);
        $this->assertSame('high', $draft->priority);
        $this->assertNotSame('', trim($draft->title));
        $this->assertNotSame('', trim($draft->description));
        $this->assertNotSame('', trim($draft->expectedImpact));
    }
}
