<?php

namespace Database\Factories;

use App\Models\Assessment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\WebsiteScan>
 */
class WebsiteScanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'url' => fake()->url(),
            'status' => 'completed',
            'pages_scanned' => 1,
            'extracted_data' => [],
            'started_at' => now(),
            'completed_at' => now(),
        ];
    }
}
