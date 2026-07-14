<?php

namespace App\Contracts;

use App\Models\Assessment;
use App\Models\Recommendation;
use App\Services\Ai\RecommendationInsight;

/**
 * Generates a merchant-specific executive explanation for a single
 * deterministic recommendation. Never decides which recommendation to
 * prioritize — that stays entirely with the existing rule-based engine.
 *
 * Nullable return (not in the original spec's signature) is deliberate: any
 * unavailability — disabled, provider failure, invalid output — must degrade
 * to "no insight" without throwing, so callers can hide the AI section
 * silently rather than handle an exception.
 */
interface RecommendationInsightGenerator
{
    public function generate(Assessment $assessment, Recommendation $recommendation): ?RecommendationInsight;
}
