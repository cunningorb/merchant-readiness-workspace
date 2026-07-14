<?php

namespace Tests\Unit\Services\WebsiteScan;

use App\Services\WebsiteScan\LlmExtractionRateLimiter;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LlmExtractionRateLimiterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'llm.requests_per_assessment_per_hour' => 2,
            'llm.requests_per_ip_per_hour' => 2,
            'llm.daily_request_limit' => 3,
        ]);

        RateLimiter::clear('llm-extraction:assessment:assessment-1');
        RateLimiter::clear('llm-extraction:ip:127.0.0.1');
        RateLimiter::clear('llm-extraction:daily:'.now()->format('Y-m-d'));
    }

    public function test_allows_requests_under_every_limit(): void
    {
        $limiter = new LlmExtractionRateLimiter;

        $this->assertFalse($limiter->tooManyAttempts('assessment-1', '127.0.0.1'));
    }

    public function test_blocks_once_the_per_assessment_limit_is_hit(): void
    {
        $limiter = new LlmExtractionRateLimiter;

        $limiter->hit('assessment-1', '10.0.0.1');
        $limiter->hit('assessment-1', '10.0.0.2');

        $this->assertTrue($limiter->tooManyAttempts('assessment-1', '10.0.0.3'));
    }

    public function test_blocks_once_the_per_ip_limit_is_hit(): void
    {
        $limiter = new LlmExtractionRateLimiter;

        $limiter->hit('assessment-a', '127.0.0.1');
        $limiter->hit('assessment-b', '127.0.0.1');

        $this->assertTrue($limiter->tooManyAttempts('assessment-c', '127.0.0.1'));
    }

    public function test_blocks_once_the_daily_limit_is_hit(): void
    {
        $limiter = new LlmExtractionRateLimiter;

        $limiter->hit('assessment-a', '10.0.0.10');
        $limiter->hit('assessment-b', '10.0.0.11');
        $limiter->hit('assessment-c', '10.0.0.12');

        $this->assertTrue($limiter->tooManyAttempts('assessment-d', '10.0.0.13'));
    }

    public function test_ip_is_optional(): void
    {
        $limiter = new LlmExtractionRateLimiter;

        $this->assertFalse($limiter->tooManyAttempts('assessment-1', null));

        $limiter->hit('assessment-1', null);

        $this->assertFalse($limiter->tooManyAttempts('assessment-1', null));
    }
}
