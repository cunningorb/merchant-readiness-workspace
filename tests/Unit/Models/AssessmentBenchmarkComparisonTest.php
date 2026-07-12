<?php

namespace Tests\Unit\Models;

use App\Models\Assessment;
use App\Models\AssessmentBenchmarkComparison;
use App\Models\BenchmarkSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentBenchmarkComparisonTest extends TestCase
{
    use RefreshDatabase;

    public function test_comparison_belongs_to_assessment(): void
    {
        $assessment = Assessment::factory()->create();
        $comparison = AssessmentBenchmarkComparison::factory()->for($assessment)->create();

        $this->assertTrue($comparison->assessment->is($assessment));
    }

    public function test_comparison_belongs_to_benchmark_set(): void
    {
        $benchmarkSet = BenchmarkSet::factory()->create();
        $comparison = AssessmentBenchmarkComparison::factory()->for($benchmarkSet)->create();

        $this->assertTrue($comparison->benchmarkSet->is($benchmarkSet));
    }

    public function test_assessment_has_many_comparisons_ordered_by_sort_order(): void
    {
        $assessment = Assessment::factory()->create();
        AssessmentBenchmarkComparison::factory()->for($assessment)->create(['sort_order' => 2, 'label' => 'Second']);
        AssessmentBenchmarkComparison::factory()->for($assessment)->create(['sort_order' => 0, 'label' => 'First']);
        AssessmentBenchmarkComparison::factory()->for($assessment)->create(['sort_order' => 1, 'label' => 'Middle']);

        $labels = $assessment->fresh()->benchmarkComparisons->pluck('label')->all();

        $this->assertSame(['First', 'Middle', 'Second'], $labels);
    }

    public function test_decimal_fields_cast_to_decimal_strings(): void
    {
        $comparison = AssessmentBenchmarkComparison::factory()->create([
            'merchant_value' => 22.5,
            'minimum_value' => 5,
            'maximum_value' => 15,
        ]);

        $fresh = $comparison->fresh();

        $this->assertSame('22.50', $fresh->merchant_value);
        $this->assertSame('5.00', $fresh->minimum_value);
        $this->assertSame('15.00', $fresh->maximum_value);
    }

    public function test_factory_creates_valid_comparison(): void
    {
        $comparison = AssessmentBenchmarkComparison::factory()->create();

        $this->assertDatabaseHas('assessment_benchmark_comparisons', ['id' => $comparison->id]);
    }
}
