<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\Report;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'token' => Str::random(40),
            'summary' => null,
            'published_at' => null,
        ];
    }
}
