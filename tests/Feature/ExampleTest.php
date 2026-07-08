<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_health_endpoint_returns_ok(): void
    {
        $response = $this->get('/health');

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'service' => 'merchant-readiness-workspace',
            ]);
    }
}
