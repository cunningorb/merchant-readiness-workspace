<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assessment>
 */
class AssessmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'status' => 'draft',
            'started_at' => now(),
            'submitted_at' => null,
        ];
    }
}
