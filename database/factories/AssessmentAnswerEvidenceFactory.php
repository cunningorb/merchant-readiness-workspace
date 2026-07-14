<?php

namespace Database\Factories;

use App\Models\Assessment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\AssessmentAnswerEvidence>
 */
class AssessmentAnswerEvidenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'question_key' => 'return_policy.window_days',
            'source_type' => 'website',
            'source_label' => 'Website scan',
            'confidence' => 'medium',
            'value' => '15-30 days',
            'evidence_url' => fake()->url(),
            'evidence_snippet' => 'Returns accepted within 30 days.',
            'metadata' => [],
        ];
    }
}
