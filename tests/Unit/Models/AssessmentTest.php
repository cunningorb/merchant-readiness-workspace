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
}
