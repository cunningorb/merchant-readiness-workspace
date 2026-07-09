<?php

namespace Tests\Unit\Models;

use App\Models\Assessment;
use App\Models\Recommendation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommendation_belongs_to_assessment(): void
    {
        $assessment = Assessment::factory()->create();
        $recommendation = Recommendation::factory()->for($assessment)->create();

        $this->assertTrue($recommendation->assessment->is($assessment));
    }

    public function test_assessment_has_many_recommendations(): void
    {
        $assessment = Assessment::factory()->create();
        Recommendation::factory()->count(2)->for($assessment)->create();

        $this->assertCount(2, $assessment->fresh()->recommendations);
    }

    public function test_expected_impact_is_nullable(): void
    {
        $recommendation = Recommendation::factory()->create(['expected_impact' => null]);

        $this->assertNull($recommendation->fresh()->expected_impact);
    }
}
