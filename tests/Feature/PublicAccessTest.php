<?php

namespace Tests\Feature;

use App\Models\Merchant;
use Database\Seeders\DemoMerchantsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_page_is_reachable_without_authentication(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Welcome'));
    }

    public function test_sample_report_redirects_to_middle_tier_demo_report(): void
    {
        $this->seed(DemoMerchantsSeeder::class);

        $report = Merchant::where('company_name', 'Northline Outdoor Supply')
            ->firstOrFail()
            ->assessments()
            ->firstOrFail()
            ->report;

        $this->get('/sample-report')
            ->assertRedirect(route('reports.show', $report->token));
    }

    public function test_privacy_page_is_reachable_without_authentication(): void
    {
        $response = $this->get('/privacy');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Legal/Privacy'));
    }

    public function test_terms_page_is_reachable_without_authentication(): void
    {
        $response = $this->get('/terms');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Legal/Terms'));
    }

    public function test_dashboard_redirects_unauthenticated_users_to_login(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_forwarded_https_requests_generate_https_urls(): void
    {
        $response = $this->withServerVariables([
            'HTTP_HOST' => 'commerce-cartographer.onrender.com',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'commerce-cartographer.onrender.com',
        ])->get('/login');

        $response->assertOk();
        $this->assertTrue($response->baseRequest->isSecure());
        $this->assertSame('https://commerce-cartographer.onrender.com', $response->baseRequest->root());
    }
}
