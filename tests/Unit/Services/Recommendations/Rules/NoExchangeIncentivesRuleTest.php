<?php

namespace Tests\Unit\Services\Recommendations\Rules;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Services\Recommendations\Rules\NoExchangeIncentivesRule;
use App\Services\Scoring\ScoreBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoExchangeIncentivesRuleTest extends TestCase
{
    use RefreshDatabase;

    private function emptyScores(): ScoreBreakdown
    {
        return new ScoreBreakdown(0, 'Foundational', []);
    }

    private function withAnswers(bool $offered, array $incentives): Assessment
    {
        $assessment = Assessment::factory()->create();
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'exchanges.offered',
            'section' => 'exchanges',
            'value' => $offered,
        ]);
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'exchanges.incentives',
            'section' => 'exchanges',
            'value' => $incentives,
        ]);

        return $assessment->fresh(['answers']);
    }

    public function test_applies_when_offered_but_no_incentives(): void
    {
        $rule = new NoExchangeIncentivesRule();

        $this->assertTrue($rule->applies($this->withAnswers(true, []), $this->emptyScores()));
    }

    public function test_does_not_apply_when_incentives_present(): void
    {
        $rule = new NoExchangeIncentivesRule();

        $this->assertFalse($rule->applies($this->withAnswers(true, ['Free shipping']), $this->emptyScores()));
    }

    public function test_does_not_apply_when_exchanges_not_offered(): void
    {
        $rule = new NoExchangeIncentivesRule();

        $this->assertFalse($rule->applies($this->withAnswers(false, []), $this->emptyScores()));
    }

    public function test_draft_has_expected_category_and_priority(): void
    {
        $assessment = Assessment::factory()->create();
        $rule = new NoExchangeIncentivesRule();

        $draft = $rule->draft($assessment, $this->emptyScores());

        $this->assertSame('exchanges', $draft->category);
        $this->assertSame('low', $draft->priority);
    }
}
