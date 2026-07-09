<?php

namespace Tests\Unit\Services\Recommendations\Rules;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Services\Recommendations\Rules\ManualReturnToolingRule;
use App\Services\Scoring\ScoreBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualReturnToolingRuleTest extends TestCase
{
    use RefreshDatabase;

    private function emptyScores(): ScoreBreakdown
    {
        return new ScoreBreakdown(0, 'Foundational', []);
    }

    private function withTools(string $value): Assessment
    {
        $assessment = Assessment::factory()->create();
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'platform.return_tools',
            'section' => 'platform',
            'value' => $value,
        ]);

        return $assessment->fresh(['answers']);
    }

    public function test_applies_when_tooling_is_email_or_spreadsheets(): void
    {
        $rule = new ManualReturnToolingRule();

        $this->assertTrue($rule->applies($this->withTools('Email/spreadsheets'), $this->emptyScores()));
    }

    public function test_does_not_apply_when_tooling_is_more_mature(): void
    {
        $rule = new ManualReturnToolingRule();

        $this->assertFalse($rule->applies($this->withTools('Returns app'), $this->emptyScores()));
    }

    public function test_draft_has_expected_category_and_priority(): void
    {
        $assessment = Assessment::factory()->create();
        $rule = new ManualReturnToolingRule();

        $draft = $rule->draft($assessment, $this->emptyScores());

        $this->assertSame('platform', $draft->category);
        $this->assertSame('high', $draft->priority);
    }
}
