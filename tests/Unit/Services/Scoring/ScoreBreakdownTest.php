<?php

namespace Tests\Unit\Services\Scoring;

use App\Services\Scoring\ScoreBreakdown;
use Tests\TestCase;

class ScoreBreakdownTest extends TestCase
{
    public function test_ranked_sections_are_sorted_ascending_by_score(): void
    {
        $breakdown = new ScoreBreakdown(
            overallScore: 62,
            overallTier: 'Developing',
            sections: [
                'return_policy' => ['score' => 80, 'tier' => 'Established'],
                'manual_operations' => ['score' => 30, 'tier' => 'Foundational'],
                'exchanges' => ['score' => 100, 'tier' => 'Advanced'],
                'platform' => ['score' => 50, 'tier' => 'Developing'],
            ],
        );

        $this->assertSame(
            ['manual_operations', 'platform', 'return_policy', 'exchanges'],
            array_keys($breakdown->rankedSections())
        );
    }

    public function test_to_array_includes_overall_and_sections(): void
    {
        $breakdown = new ScoreBreakdown(
            overallScore: 75,
            overallTier: 'Established',
            sections: ['return_policy' => ['score' => 75, 'tier' => 'Established']],
        );

        $this->assertSame(75, $breakdown->toArray()['overall_score']);
        $this->assertSame('Established', $breakdown->toArray()['overall_tier']);
        $this->assertArrayHasKey('return_policy', $breakdown->toArray()['sections']);
    }
}
