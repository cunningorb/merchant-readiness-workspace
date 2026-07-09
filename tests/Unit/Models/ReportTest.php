<?php

namespace Tests\Unit\Models;

use App\Models\Assessment;
use App\Models\Report;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_belongs_to_assessment(): void
    {
        $assessment = Assessment::factory()->create();
        $report = Report::factory()->for($assessment)->create();

        $this->assertTrue($report->assessment->is($assessment));
    }

    public function test_assessment_has_one_report(): void
    {
        $assessment = Assessment::factory()->create();
        $report = Report::factory()->for($assessment)->create();

        $this->assertTrue($assessment->fresh()->report->is($report));
    }

    public function test_token_is_generated_automatically_when_not_provided(): void
    {
        $report = Report::factory()->create(['token' => null]);

        $this->assertNotEmpty($report->token);
    }

    public function test_an_assessment_can_only_have_one_report(): void
    {
        $assessment = Assessment::factory()->create();
        Report::factory()->for($assessment)->create();

        $this->expectException(QueryException::class);

        Report::factory()->for($assessment)->create();
    }

    public function test_summary_is_nullable(): void
    {
        $report = Report::factory()->create(['summary' => null]);

        $this->assertNull($report->fresh()->summary);
    }
}
