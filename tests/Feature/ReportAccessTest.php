<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportAccessTest extends TestCase
{
    use RefreshDatabase;

    private function reportedAssessment(): Report
    {
        $assessment = Assessment::factory()->create([
            'overall_score' => 72,
            'overall_tier' => 'Established',
            'section_scores' => ['return_policy' => ['score' => 72, 'tier' => 'Established']],
        ]);

        return Report::factory()->for($assessment)->create();
    }

    public function test_api_report_endpoint_returns_expected_payload_for_valid_token(): void
    {
        $report = $this->reportedAssessment();

        $response = $this->getJson("/api/reports/{$report->token}");

        $response->assertOk()
            ->assertJsonPath('assessment.overall_score', 72)
            ->assertJsonPath('assessment.overall_tier', 'Established')
            ->assertJsonPath('merchant.company_name', $report->assessment->merchant->company_name);
    }

    public function test_api_report_endpoint_404s_for_unknown_token(): void
    {
        $response = $this->getJson('/api/reports/does-not-exist');

        $response->assertNotFound();
    }

    public function test_web_report_page_is_reachable_without_authentication(): void
    {
        $report = $this->reportedAssessment();

        $response = $this->get("/reports/{$report->token}");

        $response->assertOk();
    }

    public function test_web_report_page_404s_for_unknown_token(): void
    {
        $response = $this->get('/reports/does-not-exist');

        $response->assertNotFound();
    }
}
