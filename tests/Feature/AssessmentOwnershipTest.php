<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Models\Recommendation;
use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_answers_are_scoped_to_their_own_assessment(): void
    {
        $assessmentOne = Assessment::factory()->create();
        $assessmentTwo = Assessment::factory()->create();

        AssessmentAnswer::factory()->for($assessmentOne)->create(['question_key' => 'business.order_volume']);
        AssessmentAnswer::factory()->for($assessmentTwo)->create(['question_key' => 'business.order_volume']);

        $this->assertCount(1, $assessmentOne->fresh()->answers);
        $this->assertCount(1, $assessmentTwo->fresh()->answers);
        $this->assertNotSame(
            $assessmentOne->answers->first()->id,
            $assessmentTwo->answers->first()->id,
        );
    }

    public function test_deleting_an_assessment_cascades_to_its_children(): void
    {
        $assessment = Assessment::factory()->create();
        $answer = AssessmentAnswer::factory()->for($assessment)->create();
        $recommendation = Recommendation::factory()->for($assessment)->create();
        $report = Report::factory()->for($assessment)->create();

        $assessment->delete();

        $this->assertDatabaseMissing('assessment_answers', ['id' => $answer->id]);
        $this->assertDatabaseMissing('recommendations', ['id' => $recommendation->id]);
        $this->assertDatabaseMissing('reports', ['id' => $report->id]);
    }

    public function test_assessment_resolves_via_ulid_route_binding_not_a_plain_integer(): void
    {
        $assessment = Assessment::factory()->create();

        $resolved = (new Assessment())->resolveRouteBinding($assessment->id);

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($assessment));
        $this->assertFalse(is_numeric($assessment->getKey()));
    }
}
