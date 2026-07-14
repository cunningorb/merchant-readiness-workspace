<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Models\AssessmentAnswerEvidence;
use App\Models\Merchant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PublicAssessmentWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_wizard_page_loads_for_anonymous_visitors(): void
    {
        $this->get('/assessment')->assertOk();
    }

    public function test_anonymous_visitor_can_create_draft_assessment(): void
    {
        $response = $this->postJson('/api/assessments');

        $response
            ->assertCreated()
            ->assertJsonPath('assessment.status', 'draft');

        $assessmentId = $response->json('assessment.id');

        $this->assertSame(route('assessment.resume', $assessmentId), $response->json('assessment.resume_url'));

        $this->assertDatabaseHas('assessments', ['status' => 'draft']);
        $this->assertDatabaseHas('merchants', ['company_name' => 'Anonymous merchant']);
    }

    public function test_assessment_creation_is_rate_limited(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/assessments')->assertCreated();
        }

        $this->postJson('/api/assessments')->assertTooManyRequests();
    }

    public function test_anonymous_visitor_can_resume_draft_assessment_by_url(): void
    {
        $merchant = Merchant::factory()->create(['website' => 'https://example.test']);
        $assessment = Assessment::factory()->for($merchant)->create(['status' => 'draft']);
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'business.company_name',
            'section' => 'business',
            'value' => 'Northwind Supply',
        ]);
        AssessmentAnswerEvidence::factory()->for($assessment)->create([
            'question_key' => 'business.company_name',
            'source_type' => 'website',
            'source_label' => 'Website scan',
            'value' => 'Northwind Supply',
            'evidence_url' => 'https://example.test',
        ]);

        $this->get(route('assessment.resume', $assessment))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Assessment/Wizard')
                ->where('initialAssessment.id', $assessment->id)
                ->where('initialAssessment.status', 'draft')
                ->where('initialAssessment.resume_url', route('assessment.resume', $assessment))
                ->where('initialAssessment.answers.0.question_key', 'business.company_name')
                ->where('initialAssessment.answers.0.value', 'Northwind Supply')
                ->where('initialAssessment.evidence', fn ($evidence): bool =>
                    $evidence['business.company_name'][0]['value'] === 'Northwind Supply')
                ->where('initialAssessment.merchant.website', 'https://example.test')
                ->etc()
            );
    }

    public function test_draft_answers_are_validated_and_saved_by_question_key(): void
    {
        $assessment = Assessment::factory()->create();

        $this->postJson("/api/assessments/{$assessment->id}/answers", [
            'answers' => [
                ['question_key' => 'business.company_name', 'value' => 'Northwind Supply'],
                ['question_key' => 'business.contact_email', 'value' => 'ops@example.com'],
            ],
        ])->assertOk()->assertJsonPath('assessment.answers_count', 2);

        $this->assertDatabaseHas('assessment_answers', [
            'assessment_id' => $assessment->id,
            'question_key' => 'business.company_name',
            'section' => 'business',
        ]);
        $this->assertDatabaseHas('merchants', [
            'id' => $assessment->merchant_id,
            'company_name' => 'Northwind Supply',
            'contact_email' => 'ops@example.com',
        ]);
    }

    public function test_saving_same_question_updates_existing_draft_answer(): void
    {
        $assessment = Assessment::factory()->create();

        foreach (['5-20', '21-50'] as $value) {
            $this->postJson("/api/assessments/{$assessment->id}/answers", [
                'answers' => [
                    ['question_key' => 'manual_operations.weekly_hours', 'value' => $value],
                ],
            ])->assertOk();
        }

        $this->assertSame(1, AssessmentAnswer::where('assessment_id', $assessment->id)->count());
        $this->assertSame(['21-50'], [AssessmentAnswer::first()->value]);
    }

    public function test_unknown_question_keys_are_rejected(): void
    {
        $assessment = Assessment::factory()->create();

        $this->postJson("/api/assessments/{$assessment->id}/answers", [
            'answers' => [
                ['question_key' => 'unknown.question', 'value' => 'Nope'],
            ],
        ])->assertUnprocessable();
    }

    public function test_blank_draft_answers_are_ignored_for_partial_autosave(): void
    {
        $assessment = Assessment::factory()->create();

        $this->postJson("/api/assessments/{$assessment->id}/answers", [
            'answers' => [
                ['question_key' => 'business.company_name', 'value' => ''],
                ['question_key' => 'catalog.fit_sensitive_categories', 'value' => []],
            ],
        ])->assertOk()->assertJsonPath('assessment.answers_count', 0);

        $this->assertDatabaseCount('assessment_answers', 0);
    }

    public function test_submit_still_requires_blank_required_answers_to_be_completed(): void
    {
        $assessment = Assessment::factory()->create();

        $this->postJson("/api/assessments/{$assessment->id}/answers", [
            'answers' => [
                ['question_key' => 'business.company_name', 'value' => ''],
            ],
        ])->assertOk();

        $this->postJson("/api/assessments/{$assessment->id}/submit")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['business.company_name']);
    }

    public function test_multiselect_answers_must_only_contain_allowed_options(): void
    {
        $assessment = Assessment::factory()->create();

        $this->postJson("/api/assessments/{$assessment->id}/answers", [
            'answers' => [
                ['question_key' => 'catalog.fit_sensitive_categories', 'value' => ['Not A Real Option']],
            ],
        ])->assertUnprocessable();
    }

    public function test_multiselect_answers_accept_allowed_option_lists(): void
    {
        $assessment = Assessment::factory()->create();

        $this->postJson("/api/assessments/{$assessment->id}/answers", [
            'answers' => [
                ['question_key' => 'catalog.fit_sensitive_categories', 'value' => ['Apparel', 'Footwear']],
            ],
        ])->assertOk();

        $this->assertSame(['Apparel', 'Footwear'], AssessmentAnswer::first()->value);
    }

    public function test_select_answers_must_be_strings_from_allowed_options(): void
    {
        $assessment = Assessment::factory()->create();

        $this->postJson("/api/assessments/{$assessment->id}/answers", [
            'answers' => [
                ['question_key' => 'business.monthly_order_volume', 'value' => true],
            ],
        ])->assertUnprocessable();
    }

    public function test_null_draft_answers_are_ignored_before_database_write(): void
    {
        $assessment = Assessment::factory()->create();

        $this->postJson("/api/assessments/{$assessment->id}/answers", [
            'answers' => [
                ['question_key' => 'catalog.fit_sensitive_categories', 'value' => null],
            ],
        ])->assertOk()->assertJsonPath('assessment.answers_count', 0);

        $this->assertDatabaseCount('assessment_answers', 0);
    }

    public function test_submitted_assessments_reject_further_answer_edits(): void
    {
        $assessment = Assessment::factory()->create(['status' => 'submitted']);

        $this->postJson("/api/assessments/{$assessment->id}/answers", [
            'answers' => [
                ['question_key' => 'business.company_name', 'value' => 'Northwind Supply'],
            ],
        ])->assertStatus(409);

        $this->assertDatabaseCount('assessment_answers', 0);
    }
}
