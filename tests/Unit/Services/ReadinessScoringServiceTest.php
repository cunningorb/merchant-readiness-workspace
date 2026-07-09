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
        $this->answer($assessment, 'manual_operations.common_bottlenecks', 'manual_operations', []); // 100
        // manual_operations section average = (0 + 100) / 2 = 50 (Developing)

        $this->answer($assessment, 'exchanges.offered', 'exchanges', false); // 0
        $this->answer($assessment, 'exchanges.incentives', 'exchanges', []); // 0
        // exchanges section average = 0 (Foundational)

        $this->answer($assessment, 'platform.return_tools', 'platform', 'Custom automation'); // 100
        // platform section average = 100 (Advanced)

        $scores = app(AssessmentScorer::class)->score($assessment->fresh(['answers']));

        $this->assertSame(84, $scores->sections['return_policy']['score']);
        $this->assertSame('Established', $scores->sections['return_policy']['tier']);

        $this->assertSame(50, $scores->sections['manual_operations']['score']);
        $this->assertSame('Developing', $scores->sections['manual_operations']['tier']);

        $this->assertSame(0, $scores->sections['exchanges']['score']);
        $this->assertSame('Foundational', $scores->sections['exchanges']['tier']);

        $this->assertSame(100, $scores->sections['platform']['score']);
        $this->assertSame('Advanced', $scores->sections['platform']['tier']);

        // overall = (84*30 + 50*30 + 0*20 + 100*20) / 100 = (2520 + 1500 + 0 + 2000) / 100 = 60.4 -> 60
        $this->assertSame(60, $scores->overallScore);
        $this->assertSame('Developing', $scores->overallTier);
    }
}
