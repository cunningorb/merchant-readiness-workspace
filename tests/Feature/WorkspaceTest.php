<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Merchant;
use App\Models\Recommendation;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_lists_only_submitted_assessments(): void
    {
        $user = User::factory()->create();
        $submitted = Assessment::factory()->create([
            'status' => 'submitted',
            'submitted_at' => now(),
            'overall_score' => 72,
            'overall_tier' => 'Established',
        ]);
        Assessment::factory()->create(['status' => 'draft']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Workspace/Index')
            ->has('assessments.data', 1)
            ->where('assessments.data.0.id', $submitted->id)
        );
    }

    public function test_search_filters_by_company_name(): void
    {
        $user = User::factory()->create();
        $match = Merchant::factory()->create(['company_name' => 'Acme Corp']);
        $other = Merchant::factory()->create(['company_name' => 'Globex Inc']);
        Assessment::factory()->for($match)->create(['status' => 'submitted', 'submitted_at' => now()]);
        Assessment::factory()->for($other)->create(['status' => 'submitted', 'submitted_at' => now()]);

        $response = $this->actingAs($user)->get('/dashboard?search=Acme');

        $response->assertInertia(fn ($page) => $page
            ->has('assessments.data', 1)
            ->where('assessments.data.0.merchant.company_name', 'Acme Corp')
        );
    }

    public function test_search_filters_by_contact_name(): void
    {
        $user = User::factory()->create();
        $match = Merchant::factory()->create(['contact_name' => 'Jane Doe']);
        Assessment::factory()->for($match)->create(['status' => 'submitted', 'submitted_at' => now()]);
        $other = Merchant::factory()->create(['contact_name' => 'Raj Patel']);
        Assessment::factory()->for($other)->create(['status' => 'submitted', 'submitted_at' => now()]);

        $response = $this->actingAs($user)->get('/dashboard?search=Jane');

        $response->assertInertia(fn ($page) => $page
            ->has('assessments.data', 1)
            ->where('assessments.data.0.merchant.contact_name', 'Jane Doe')
        );
    }

    public function test_sorts_by_submitted_date(): void
    {
        $user = User::factory()->create();
        $older = Assessment::factory()->create(['status' => 'submitted', 'submitted_at' => now()->subDays(5)]);
        $newer = Assessment::factory()->create(['status' => 'submitted', 'submitted_at' => now()]);

        $response = $this->actingAs($user)->get('/dashboard?sort=submitted_at&direction=asc');

        $response->assertInertia(fn ($page) => $page
            ->where('assessments.data.0.id', $older->id)
            ->where('assessments.data.1.id', $newer->id)
        );
    }

    public function test_sorts_by_tier_score(): void
    {
        $user = User::factory()->create();
        $low = Assessment::factory()->create(['status' => 'submitted', 'submitted_at' => now(), 'overall_score' => 20, 'overall_tier' => 'Foundational']);
        $high = Assessment::factory()->create(['status' => 'submitted', 'submitted_at' => now(), 'overall_score' => 90, 'overall_tier' => 'Advanced']);

        $response = $this->actingAs($user)->get('/dashboard?sort=tier&direction=asc');

        $response->assertInertia(fn ($page) => $page
            ->where('assessments.data.0.id', $low->id)
            ->where('assessments.data.1.id', $high->id)
        );
    }

    public function test_sorts_by_company_name(): void
    {
        $user = User::factory()->create();
        $zCorp = Merchant::factory()->create(['company_name' => 'Zeta Corp']);
        $aCorp = Merchant::factory()->create(['company_name' => 'Acme Corp']);
        $z = Assessment::factory()->for($zCorp)->create(['status' => 'submitted', 'submitted_at' => now()]);
        $a = Assessment::factory()->for($aCorp)->create(['status' => 'submitted', 'submitted_at' => now()]);

        $response = $this->actingAs($user)->get('/dashboard?sort=company&direction=asc');

        $response->assertInertia(fn ($page) => $page
            ->where('assessments.data.0.id', $a->id)
            ->where('assessments.data.1.id', $z->id)
        );
    }

    public function test_shows_assessment_review_with_merchant_and_talking_points(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->create([
            'company_name' => 'Acme Corp',
            'contact_name' => 'Jane Doe',
            'contact_email' => 'jane@acme.com',
            'website' => 'acme.com',
        ]);
        $assessment = Assessment::factory()->for($merchant)->create([
            'status' => 'submitted',
            'submitted_at' => now(),
            'overall_score' => 72,
            'overall_tier' => 'Established',
            'section_scores' => ['return_policy' => ['score' => 72, 'tier' => 'Established']],
        ]);
        Report::factory()->for($assessment)->create(['published_at' => now()]);

        Recommendation::factory()->for($assessment)->create(['priority' => 'low', 'title' => 'Low priority item']);
        Recommendation::factory()->for($assessment)->create(['priority' => 'high', 'title' => 'High priority item']);
        Recommendation::factory()->for($assessment)->create(['priority' => 'medium', 'title' => 'Medium priority item']);
        Recommendation::factory()->for($assessment)->create(['priority' => 'high', 'title' => 'Second high priority item']);

        $response = $this->actingAs($user)->get("/dashboard/assessments/{$assessment->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Workspace/Show')
            ->where('report.merchant.company_name', 'Acme Corp')
            ->where('report.merchant.contact_name', 'Jane Doe')
            ->where('report.merchant.contact_email', 'jane@acme.com')
            ->where('report.merchant.website', 'acme.com')
            ->where('report.assessment.overall_score', 72)
            ->has('report.talking_points', 3)
            ->where('report.talking_points.0.title', 'High priority item')
            ->where('report.talking_points.1.title', 'Second high priority item')
            ->where('report.talking_points.2.title', 'Medium priority item')
        );
    }

    public function test_show_404s_for_unknown_assessment(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard/assessments/does-not-exist');

        $response->assertNotFound();
    }

    public function test_show_404s_for_draft_assessment(): void
    {
        $user = User::factory()->create();
        $assessment = Assessment::factory()->create(['status' => 'draft']);

        $response = $this->actingAs($user)->get("/dashboard/assessments/{$assessment->id}");

        $response->assertNotFound();
    }

    public function test_show_requires_authentication(): void
    {
        $merchant = Merchant::factory()->create();
        $assessment = Assessment::factory()->for($merchant)->create(['status' => 'submitted', 'submitted_at' => now()]);
        Report::factory()->for($assessment)->create(['published_at' => now()]);

        $response = $this->get("/dashboard/assessments/{$assessment->id}");

        $response->assertRedirect('/login');
    }

    public function test_show_creates_missing_report_for_submitted_assessment_without_one(): void
    {
        $user = User::factory()->create();
        $assessment = Assessment::factory()->create([
            'status' => 'submitted',
            'submitted_at' => now(),
            'overall_score' => 50,
            'overall_tier' => 'Developing',
            'section_scores' => ['return_policy' => ['score' => 50, 'tier' => 'Developing']],
        ]);

        $this->assertDatabaseCount('reports', 0);

        $response = $this->actingAs($user)->get("/dashboard/assessments/{$assessment->id}");

        $response->assertOk();
        $this->assertDatabaseCount('reports', 1);
        $this->assertNotNull($assessment->fresh()->report);
    }
}
