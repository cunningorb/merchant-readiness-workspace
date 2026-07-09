<?php

namespace Tests\Unit\Models;

use App\Models\Assessment;
use App\Models\Merchant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_assessment_belongs_to_merchant(): void
    {
        $merchant = Merchant::factory()->create();
        $assessment = Assessment::factory()->for($merchant)->create();

        $this->assertTrue($assessment->merchant->is($merchant));
    }

    public function test_assessment_uses_a_ulid_as_its_route_key(): void
    {
        $assessment = Assessment::factory()->create();

        $this->assertSame('id', $assessment->getRouteKeyName());
        // Laravel's HasUlids::newUniqueId() lowercases the generated ULID
        // (strtolower((string) Str::ulid())) to avoid MySQL collation/index
        // issues with mixed-case values, so match case-insensitively.
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $assessment->id);
    }

    public function test_assessment_defaults_to_draft_status(): void
    {
        $assessment = Assessment::factory()->create();

        $this->assertSame('draft', $assessment->status);
    }

    public function test_answer_value_returns_stored_value_for_question_key(): void
    {
        $assessment = Assessment::factory()->create();
        \App\Models\AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'return_policy.window_days',
            'section' => 'return_policy',
            'value' => '31-60 days',
        ]);

        $this->assertSame('31-60 days', $assessment->answerValue('return_policy.window_days'));
    }

    public function test_answer_value_returns_null_when_question_unanswered(): void
    {
        $assessment = Assessment::factory()->create();

        $this->assertNull($assessment->answerValue('return_policy.window_days'));
    }

    public function test_score_fields_are_nullable_and_section_scores_casts_to_array(): void
    {
        $assessment = Assessment::factory()->create();

        $this->assertNull($assessment->overall_score);
        $this->assertNull($assessment->overall_tier);
        $this->assertNull($assessment->section_scores);

        $assessment->forceFill([
            'overall_score' => 72,
            'overall_tier' => 'Established',
            'section_scores' => ['return_policy' => ['score' => 80, 'tier' => 'Established']],
        ])->save();

        $fresh = $assessment->fresh();
        $this->assertSame(72, $fresh->overall_score);
        $this->assertSame('Established', $fresh->overall_tier);
        $this->assertIsArray($fresh->section_scores);
        $this->assertSame(80, $fresh->section_scores['return_policy']['score']);
    }
}
