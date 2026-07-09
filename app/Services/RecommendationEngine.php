<?php

namespace App\Services;

use App\Contracts\RecommendationRule;
use App\Models\Assessment;
use App\Services\Recommendations\RecommendationDraft;
use App\Services\Scoring\ScoreBreakdown;
use Illuminate\Support\Collection;

class RecommendationEngine
{
    private const PRIORITY_ORDER = ['high' => 0, 'medium' => 1, 'low' => 2];

    /**
     * @param RecommendationRule[] $rules
     */
    public function __construct(private readonly array $rules)
    {
    }

    /**
     * @return Collection<int, RecommendationDraft>
     */
    public function generate(Assessment $assessment, ScoreBreakdown $scores): Collection
    {
        return collect($this->rules)
            ->filter(fn (RecommendationRule $rule) => $rule->applies($assessment, $scores))
            ->map(fn (RecommendationRule $rule) => $rule->draft($assessment, $scores))
            ->sortBy(fn (RecommendationDraft $draft) => self::PRIORITY_ORDER[$draft->priority] ?? 99)
            ->values();
    }
}
