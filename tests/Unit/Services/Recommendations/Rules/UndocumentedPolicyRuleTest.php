<?php

namespace Tests\Unit\Services\Recommendations\Rules;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Services\Recommendations\Rules\UndocumentedPolicyRule;
use App\Services\Scoring\ScoreBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UndocumentedPolicyRuleTest extends TestCase
{
    use RefreshDatabase;

    private function emptyScores(): ScoreBreakdown
    {
        return new ScoreBreakdown(0, 'Foundational', []);
    }

    private function withClarity(string $value): Assessment
    {
        $assessment = Assessment::factory()->create();
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'return_policy.policy_clarity',
            'section' => 'return_policy',
            'value' => $value,
        ]);

        return $assessment->fresh(['answers']);
    }

    public function test_applies_when_policy_is_not_documented(): void
    {
        $rule = new UndocumentedPolicyRule();

        $this->assertTrue($rule->applies($this->withClarity('Not documented'), $this->emptyScores()));
    }

    public function test_applies_when_policy_is_only_a_basic_faq(): void
    {
        $rule = new UndocumentedPolicyRule();

        $this->assertTrue($rule->applies($this->withClarity('Basic FAQ'), $this->emptyScores()));
    }

    public function test_does_not_apply_when_policy_is_detailed(): void
    {
        $rule = new UndocumentedPolicyRule();

        $this->assertFalse($rule->applies($this->withClarity('Detailed policy page'), $this->emptyScores()));
    }

    public function test_draft_has_expected_category_and_priority(): void
    {
        $assessment = Assessment::factory()->create();
        $rule = new UndocumentedPolicyRule();

        $draft = $rule->draft($assessment, $this->emptyScores());

        $this->assertSame('return_policy', $draft->category);
        $this->assertSame('medium', $draft->priority);
    }
}
