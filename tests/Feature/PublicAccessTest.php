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
    }

    public function test_dashboard_redirects_unauthenticated_users_to_login(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }
}
