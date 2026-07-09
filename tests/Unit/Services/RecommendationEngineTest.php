<?php

namespace Tests\Unit\Services;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Services\RecommendationEngine;
use App\Services\Scoring\ScoreBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_only_recommendations_whose_rules_apply_sorted_by_priority(): void
    {
        $assessment = Assessment::factory()->create();

        // Triggers ManualReturnToolingRule (high) and UndocumentedPolicyRule (medium).
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'platform.return_tools',
            'section' => 'platform',
            'value' => 'Email/spreadsheets',
        ]);
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'return_policy.policy_clarity',
            'section' => 'return_policy',
            'value' => 'Basic FAQ',
        ]);
        // Does not trigger ShortReturnWindowRule.
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'return_policy.window_days',
            'section' => 'return_policy',
            'value' => 'More than 60 days',
        ]);

        $scores = new ScoreBreakdown(0, 'Foundational', []);
        $drafts = app(RecommendationEngine::class)->generate($assessment->fresh(['answers']), $scores);

        $this->assertCount(2, $drafts);
        $this->assertSame('high', $drafts->first()->priority);
        $this->assertSame('platform', $drafts->first()->category);
        $this->assertSame('medium', $drafts->last()->priority);
        $this->assertSame('return_policy', $drafts->last()->category);
    }

    public function test_generates_no_recommendations_when_no_rule_applies(): void
    {
        $assessment = Assessment::factory()->create();
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'return_policy.window_days',
            'section' => 'return_policy',
            'value' => 'More than 60 days',
        ]);
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'return_policy.policy_clarity',
            'section' => 'return_policy',
            'value' => 'Contextual policy by product/order',
        ]);
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'exchanges.offered',
            'section' => 'exchanges',
            'value' => true,
        ]);
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'exchanges.incentives',
            'section' => 'exchanges',
            'value' => ['Free shipping'],
        ]);
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'manual_operations.weekly_hours',
            'section' => 'manual_operations',
            'value' => 'Under 5',
        ]);
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'manual_operations.common_bottlenecks',
            'section' => 'manual_operations',
            'value' => [],
        ]);
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'platform.return_tools',
            'section' => 'platform',
            'value' => 'Custom automation',
        ]);

        $scores = new ScoreBreakdown(100, 'Advanced', []);
        $drafts = app(RecommendationEngine::class)->generate($assessment->fresh(['answers']), $scores);

        $this->assertCount(0, $drafts);
    }
}
