<?php

namespace Tests\Unit\Services;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Services\SubmitAssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SubmitAssessmentServiceTest extends TestCase
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
            ['question_key' => 'return_policy.window_days', 'section' => 'return_policy', 'value' => 'More than 60 days'], // 100
            ['question_key' => 'return_policy.policy_clarity', 'section' => 'return_policy', 'value' => 'Contextual policy by product/order'], // 100 -> return_policy avg 100
            ['question_key' => 'exchanges.offered', 'section' => 'exchanges', 'value' => true], // 100
            ['question_key' => 'exchanges.incentives', 'section' => 'exchanges', 'value' => ['Bonus credit', 'Free shipping', 'Instant exchange', 'Size recommendations']], // 100 -> exchanges avg 100
            ['question_key' => 'manual_operations.weekly_hours', 'section' => 'manual_operations', 'value' => 'Under 5'], // 100
            ['question_key' => 'manual_operations.common_bottlenecks', 'section' => 'manual_operations', 'value' => []], // 100 -> manual_operations avg 100
            ['question_key' => 'platform.ecommerce_platform', 'section' => 'platform', 'value' => 'Shopify'],
            ['question_key' => 'platform.return_tools', 'section' => 'platform', 'value' => 'Custom automation'], // 100 -> platform avg 100
        ];
        // overall = (100*30 + 100*30 + 100*20 + 100*20) / 100 = 100 (Advanced); no rule triggers -> 0 recommendations

        foreach ($answers as $answer) {
            AssessmentAnswer::factory()->for($assessment)->create($answer);
        }

        return $assessment->fresh(['answers']);
    }

    public function test_submits_a_complete_assessment_and_persists_score_and_recommendations(): void
    {
        $assessment = $this->completeAssessment();

        $result = app(SubmitAssessmentService::class)->submit($assessment);

        $this->assertSame('submitted', $result->status);
        $this->assertNotNull($result->submitted_at);
        $this->assertSame(100, $result->overall_score);
        $this->assertSame('Advanced', $result->overall_tier);
        $this->assertIsArray($result->section_scores);
        $this->assertDatabaseHas('assessments', ['id' => $assessment->id, 'status' => 'submitted']);
        $this->assertCount(0, $result->recommendations);
    }

    public function test_rejects_submission_when_a_required_question_is_unanswered(): void
    {
        $assessment = Assessment::factory()->create();

        $this->expectException(ValidationException::class);

        app(SubmitAssessmentService::class)->submit($assessment);
    }

    public function test_rejects_resubmission_of_an_already_submitted_assessment(): void
    {
        $assessment = $this->completeAssessment();
        app(SubmitAssessmentService::class)->submit($assessment);

        try {
            app(SubmitAssessmentService::class)->submit($assessment->fresh(['answers']));
            $this->fail('Expected an HttpException to be thrown.');
        } catch (HttpException $e) {
            // HttpException::getCode() is not the HTTP status - that's getStatusCode().
            $this->assertSame(409, $e->getStatusCode());
        }
    }
}
