<?php

namespace Tests\Feature;

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
