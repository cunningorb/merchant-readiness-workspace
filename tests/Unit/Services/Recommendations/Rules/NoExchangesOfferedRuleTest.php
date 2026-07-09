<?php

namespace Tests\Unit\Services\Recommendations\Rules;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Services\Recommendations\Rules\NoExchangesOfferedRule;
use App\Services\Scoring\ScoreBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoExchangesOfferedRuleTest extends TestCase
{
    use RefreshDatabase;

    private function emptyScores(): ScoreBreakdown
    {
        return new ScoreBreakdown(0, 'Foundational', []);
    }

    private function withOffered(bool $value): Assessment
    {
        $assessment = Assessment::factory()->create();
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'exchanges.offered',
            'section' => 'exchanges',
            'value' => $value,
        ]);

        return $assessment->fresh(['answers']);
    }

    public function test_applies_when_exchanges_not_offered(): void
    {
        $rule = new NoExchangesOfferedRule();

        $this->assertTrue($rule->applies($this->withOffered(false), $this->emptyScores()));
    }

    public function test_does_not_apply_when_exchanges_offered(): void
    {
        $rule = new NoExchangesOfferedRule();

        $this->assertFalse($rule->applies($this->withOffered(true), $this->emptyScores()));
    }

    public function test_draft_has_expected_category_and_priority(): void
    {
        $assessment = Assessment::factory()->create();
        $rule = new NoExchangesOfferedRule();

        $draft = $rule->draft($assessment, $this->emptyScores());

        $this->assertSame('exchanges', $draft->category);
        $this->assertSame('high', $draft->priority);
    }
}
