<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentAnswer>
 */
class AssessmentAnswerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'question_key' => 'section.'.fake()->unique()->word(),
            'section' => fake()->randomElement([
                'business', 'catalog', 'policy', 'exchanges', 'operations', 'platform',
            ]),
            'value' => ['answer' => fake()->word()],
        ];
    }
}
