<?php

namespace App\Services\Recommendations;

final class RecommendationDraft
{
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly string $category,
        public readonly string $priority,
        public readonly string $expectedImpact,
    ) {
    }
}
