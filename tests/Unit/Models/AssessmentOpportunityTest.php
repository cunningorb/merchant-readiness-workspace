<?php

namespace Tests\Unit\Models;

use App\Models\Assessment;
use App\Models\AssessmentOpportunity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentOpportunityTest extends TestCase
{
    use RefreshDatabase;

    public function test_opportunity_belongs_to_assessment(): void
    {
        $assessment = Assessment::factory()->create();
        $opportunity = AssessmentOpportunity::factory()->for($assessment)->create();

        $this->assertTrue($opportunity->assessment->is($assessment));
    }

    public function test_assessment_has_many_opportunities_ordered_by_sort_order(): void
    {
        $assessment = Assessment::factory()->create();
        AssessmentOpportunity::factory()->for($assessment)->create(['sort_order' => 2, 'title' => 'Second']);
        AssessmentOpportunity::factory()->for($assessment)->create(['sort_order' => 0, 'title' => 'First']);
        AssessmentOpportunity::factory()->for($assessment)->create(['sort_order' => 1, 'title' => 'Middle']);

        $titles = $assessment->fresh()->opportunities->pluck('title')->all();

        $this->assertSame(['First', 'Middle', 'Second'], $titles);
    }

    public function test_assumptions_and_evidence_cast_to_array(): void
    {
        $opportunity = AssessmentOpportunity::factory()->create([
            'assumptions' => ['average_order_value' => 75.5],
            'evidence' => [
                'inputs' => ['x' => 1],
                'source_answer_keys' => ['a.b'],
                'why' => ['because'],
            ],
        ]);

        $fresh = $opportunity->fresh();

        $this->assertIsArray($fresh->assumptions);
        $this->assertSame(75.5, $fresh->assumptions['average_order_value']);
        $this->assertIsArray($fresh->evidence);
        $this->assertSame(['a.b'], $fresh->evidence['source_answer_keys']);
    }

    public function test_minimum_and_maximum_value_cast_to_decimal_strings(): void
    {
        $opportunity = AssessmentOpportunity::factory()->create([
            'minimum_value' => 1000,
            'maximum_value' => 2500.5,
        ]);

        $fresh = $opportunity->fresh();

        $this->assertSame('1000.00', $fresh->minimum_value);
        $this->assertSame('2500.50', $fresh->maximum_value);
    }

    public function test_minimum_and_maximum_value_are_nullable(): void
    {
        $opportunity = AssessmentOpportunity::factory()->create([
            'minimum_value' => null,
            'maximum_value' => null,
        ]);

        $fresh = $opportunity->fresh();

        $this->assertNull($fresh->minimum_value);
        $this->assertNull($fresh->maximum_value);
    }

    public function test_factory_creates_valid_opportunity(): void
    {
        $opportunity = AssessmentOpportunity::factory()->create();

        $this->assertDatabaseHas('assessment_opportunities', ['id' => $opportunity->id]);
    }
}
