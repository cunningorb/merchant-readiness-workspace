<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function completeAssessment(): Assessment
    {
        $assessment = Assessment::factory()->create();

        $answers = [
            ['question_key' => 'business.company_name', 'section' => 'business', 'value' => 'Northwind Supply'],
            ['question_key' => 'business.contact_email', 'section' => 'business', 'value' => 'ops@example.com'],
            ['question_key' => 'business.monthly_order_volume', 'section' => 'business', 'value' => '1,000-10,000'],
            ['question_key' => 'catalog.sku_count', 'section' => 'catalog', 'value' => '500-5,000'],
            ['question_key' => 'catalog.fit_sensitive_categories', 'section' => 'catalog', 'value' => []],
            ['question_key' => 'return_policy.window_days', 'section' => 'return_policy', 'value' => '14 days or less'], // 0
            ['question_key' => 'return_policy.policy_clarity', 'section' => 'return_policy', 'value' => 'Not documented'], // 0 -> return_policy avg 0
            ['question_key' => 'exchanges.offered', 'section' => 'exchanges', 'value' => false], // 0
            ['question_key' => 'exchanges.incentives', 'section' => 'exchanges', 'value' => []], // 0 -> exchanges avg 0
            ['question_key' => 'manual_operations.weekly_hours', 'section' => 'manual_operations', 'value' => '50+'], // 0
            ['question_key' => 'manual_operations.common_bottlenecks', 'section' => 'manual_operations', 'value' => ['Approvals', 'Labels']], // 100-(2/5)*100=60 -> manual_operations avg 30
            ['question_key' => 'platform.ecommerce_platform', 'section' => 'platform', 'value' => 'Shopify'],
            ['question_key' => 'platform.return_tools', 'section' => 'platform', 'value' => 'Email/spreadsheets'], // 0 -> platform avg 0
        ];
        // overall = (0*30 + 30*30 + 0*20 + 0*20) / 100 = 9 (Foundational)
        // triggered rules: ShortReturnWindow, UndocumentedPolicy, NoExchangesOffered, HighManualHours, ReturnBottlenecks, ManualReturnTooling = 6

        foreach ($answers as $answer) {
            AssessmentAnswer::factory()->for($assessment)->create($answer);
        }

        return $assessment;
    }

    public function test_submitting_a_complete_assessment_returns_score_and_recommendations(): void
    {
        $assessment = $this->completeAssessment();

        $response = $this->postJson("/api/assessments/{$assessment->id}/submit");

        $response->assertOk()
            ->assertJsonPath('assessment.status', 'submitted')
            ->assertJsonPath('assessment.overall_score', 9)
            ->assertJsonPath('assessment.overall_tier', 'Foundational');

        $response->assertJsonCount(6, 'recommendations');
        $this->assertDatabaseHas('assessments', ['id' => $assessment->id, 'status' => 'submitted']);
        $this->assertDatabaseCount('recommendations', 6);
    }

    public function test_submitting_an_incomplete_assessment_is_rejected(): void
    {
        $assessment = Assessment::factory()->create();

        $response = $this->postJson("/api/assessments/{$assessment->id}/submit");

        $response->assertUnprocessable();
        $response->assertJsonStructure(['errors']);
        $this->assertDatabaseHas('assessments', ['id' => $assessment->id, 'status' => 'draft']);
    }

    public function test_submitting_an_already_submitted_assessment_is_rejected(): void
    {
        $assessment = $this->completeAssessment();
        $this->postJson("/api/assessments/{$assessment->id}/submit")->assertOk();

        $response = $this->postJson("/api/assessments/{$assessment->id}/submit");

        $response->assertStatus(409);
    }
}
