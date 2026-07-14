<?php

namespace App\Services\WebsiteScan;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Three independent limits — per-assessment, per-IP, and an application-wide
 * daily cap — checked together so any one of them can force a fall back to
 * the rules strategy. Deliberately stricter than the provider's own
 * account-level limits.
 */
class LlmExtractionRateLimiter
{
    public function tooManyAttempts(string $assessmentId, ?string $clientIp): bool
    {
        if (RateLimiter::tooManyAttempts($this->assessmentKey($assessmentId), (int) config('llm.requests_per_assessment_per_hour', 3))) {
            return true;
        }

        if ($clientIp !== null && RateLimiter::tooManyAttempts($this->ipKey($clientIp), (int) config('llm.requests_per_ip_per_hour', 10))) {
            return true;
        }

        return RateLimiter::tooManyAttempts($this->dailyKey(), (int) config('llm.daily_request_limit', 100));
    }

    public function hit(string $assessmentId, ?string $clientIp): void
    {
        RateLimiter::hit($this->assessmentKey($assessmentId), 3600);

        if ($clientIp !== null) {
            RateLimiter::hit($this->ipKey($clientIp), 3600);
        }

        RateLimiter::hit($this->dailyKey(), 86400);
    }

    private function assessmentKey(string $assessmentId): string
    {
        return "llm-extraction:assessment:{$assessmentId}";
    }

    private function ipKey(string $clientIp): string
    {
        return "llm-extraction:ip:{$clientIp}";
    }

    private function dailyKey(): string
    {
        return 'llm-extraction:daily:'.now()->format('Y-m-d');
    }
}
