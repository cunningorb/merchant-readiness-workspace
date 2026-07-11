<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Merchant;
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
}
