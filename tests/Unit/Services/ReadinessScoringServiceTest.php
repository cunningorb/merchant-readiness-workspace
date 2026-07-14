<?php

namespace Tests\Unit\Services;

use App\Contracts\AssessmentScorer;
use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadinessScoringServiceTest extends TestCase
{
    use RefreshDatabase;

    private function answer(Assessment $assessment, string $questionKey, string $section, mixed $value): void
    {
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => $questionKey,
            'section' => $section,
            'value' => $value,
        ]);
    }

    public function test_computes_section_scores_weighted_overall_score_and_tiers(): void
    {
        $assessment = Assessment::factory()->create();

        $this->answer($assessment, 'return_policy.window_days', 'return_policy', 'More than 60 days'); // 100
        $this->answer($assessment, 'return_policy.policy_clarity', 'return_policy', 'Detailed policy page'); // 67
        // return_policy section average = (100 + 67) / 2 = 83.5 -> round to 84 (Established)

        $this->answer($assessment, 'manual_operations.weekly_hours', 'manual_operations', '50+'); // 0
        $this->answer($assessment, 'manual_operations.common_bottlenecks', 'manual_operations', ['Approvals', 'Labels']); // 60
        // manual_operations section average = (0 + 60) / 2 = 30 (Foundational)

        $this->answer($assessment, 'exchanges.offered', 'exchanges', false); // 0
        $this->answer($assessment, 'exchanges.incentives', 'exchanges', ['Bonus credit', 'Free shipping']); // 50
        // exchanges section average = (0 + 50) / 2 = 25 (Foundational)

        $this->answer($assessment, 'platform.return_tools', 'platform', 'Custom automation'); // 100
        // platform section average = 100 (Advanced)

        $scores = app(AssessmentScorer::class)->score($assessment->fresh(['answers']));

        $this->assertSame(84, $scores->sections['return_policy']['score']);
        $this->assertSame('Established', $scores->sections['return_policy']['tier']);

        $this->assertSame(30, $scores->sections['manual_operations']['score']);
        $this->assertSame('Foundational', $scores->sections['manual_operations']['tier']);

        $this->assertSame(25, $scores->sections['exchanges']['score']);
        $this->assertSame('Foundational', $scores->sections['exchanges']['tier']);

        $this->assertSame(100, $scores->sections['platform']['score']);
        $this->assertSame('Advanced', $scores->sections['platform']['tier']);

        // overall = (84*30 + 30*30 + 25*20 + 100*20) / 100 = 59.2 -> 59
        $this->assertSame(59, $scores->overallScore);
        $this->assertSame('Developing', $scores->overallTier);
    }

    public function test_unanswered_optional_questions_are_not_counted_in_section_averages(): void
    {
        $assessment = Assessment::factory()->create();

        $this->answer($assessment, 'manual_operations.weekly_hours', 'manual_operations', '50+'); // 0
        $this->answer($assessment, 'manual_operations.common_bottlenecks', 'manual_operations', []); // optional blank, skipped
        $this->answer($assessment, 'exchanges.offered', 'exchanges', true); // 100

        $scores = app(AssessmentScorer::class)->score($assessment->fresh(['answers']));

        $this->assertSame(0, $scores->sections['manual_operations']['score']);
        $this->assertSame(100, $scores->sections['exchanges']['score']);
    }
}
