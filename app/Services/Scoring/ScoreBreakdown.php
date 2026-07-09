<?php

namespace App\Services\Scoring;

final class ScoreBreakdown
{
    /**
     * @param array<string, array{score: int, tier: string}> $sections
     */
    public function __construct(
        public readonly int $overallScore,
        public readonly string $overallTier,
        public readonly array $sections,
    ) {
    }

    /**
     * @return array<string, array{score: int, tier: string}>
     */
    public function rankedSections(): array
    {
        $sections = $this->sections;
        uasort($sections, fn (array $a, array $b) => $a['score'] <=> $b['score']);

        return $sections;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'overall_score' => $this->overallScore,
            'overall_tier' => $this->overallTier,
            'sections' => $this->sections,
            'ranked_sections' => $this->rankedSections(),
        ];
    }
}
