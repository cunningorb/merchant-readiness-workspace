<?php

namespace App\Services\WebsiteScan;

use App\Contracts\WebsiteExtractionStrategy;
use App\Models\Assessment;
use App\Models\AssessmentAnswerEvidence;
use App\Models\WebsiteScan;
use App\Support\SafePublicHttpUrl;
use Illuminate\Support\Facades\Log;

class StartWebsiteScanService
{
    public function __construct(
        private readonly WebsiteCrawler $crawler,
        private readonly WebsiteExtractionStrategyResolver $strategyResolver,
        private readonly ApplyWebsiteEvidenceToAnswersService $applyWebsiteEvidenceToAnswers,
        private readonly LlmExtractionRateLimiter $rateLimiter,
    ) {}

    public function start(Assessment $assessment, string $url, ?string $clientIp = null): WebsiteScan
    {
        $normalizedUrl = $this->normalizeUrl($url);
        $previousScanUrl = $assessment->websiteScans()
            ->latest('id')
            ->value('url');

        if ($previousScanUrl !== null && $previousScanUrl !== $normalizedUrl) {
            $this->applyWebsiteEvidenceToAnswers->clearPreviousWebsiteAnswers($assessment);
        }

        $startedAt = now();
        $scanResult = $this->crawler->crawl($normalizedUrl);
        $strategy = $this->resolveStrategy($assessment, $clientIp);
        $suggestions = $strategy->extract($scanResult);

        $scan = $assessment->websiteScans()->create([
            'url' => $normalizedUrl,
            'status' => 'completed',
            'pages_scanned' => count($scanResult->pages),
            'extracted_data' => $suggestions,
            'started_at' => $startedAt,
            'completed_at' => now(),
        ]);

        $assessment->merchant()->update(['website' => $normalizedUrl]);

        $evidence = collect($suggestions)->map(fn (array $suggestion): AssessmentAnswerEvidence => AssessmentAnswerEvidence::create([
                'assessment_id' => $assessment->id,
                'question_key' => $suggestion['question_key'],
                'source_type' => 'website',
                'source_label' => ($suggestion['provider'] ?? 'rules') === 'rules'
                    ? 'Website scan'
                    : 'Website scan — AI-assisted',
                'provider' => $suggestion['provider'] ?? 'rules',
                'model' => $suggestion['model'] ?? null,
                'prompt_version' => $suggestion['prompt_version'] ?? null,
                'confidence' => $suggestion['confidence'],
                'requires_confirmation' => $suggestion['requires_confirmation'] ?? false,
                'value' => $suggestion['value'],
                'evidence_url' => $suggestion['evidence_url'] ?? $normalizedUrl,
                'evidence_snippet' => $suggestion['evidence_snippet'] ?? null,
                'metadata' => array_merge($suggestion['metadata'] ?? [], [
                    'website_scan_id' => $scan->id,
                ]),
            ]));

        $this->applyWebsiteEvidenceToAnswers->apply($assessment, $evidence);

        return $scan->fresh('assessment.answerEvidence', 'assessment.answers');
    }

    /**
     * Rate limiting lives here rather than inside the LLM/hybrid strategies:
     * they only ever see a WebsiteScanResult (no assessment or request
     * context), which is what keeps them from ever touching merchant answer
     * data. Exceeding any limit degrades to the rules strategy for this call
     * only — the scan still completes normally (see item 14 of the spec).
     */
    private function resolveStrategy(Assessment $assessment, ?string $clientIp): WebsiteExtractionStrategy
    {
        $strategy = $this->strategyResolver->resolve();

        if (! ($strategy instanceof LlmWebsiteExtractionStrategy || $strategy instanceof HybridWebsiteExtractionStrategy)) {
            return $strategy;
        }

        if ($this->rateLimiter->tooManyAttempts($assessment->id, $clientIp)) {
            Log::warning('LLM extraction rate limit reached; falling back to rules-only evidence.', [
                'assessment_id' => $assessment->id,
            ]);

            return app(RulesWebsiteExtractionStrategy::class);
        }

        $this->rateLimiter->hit($assessment->id, $clientIp);

        return $strategy;
    }

    private function normalizeUrl(string $url): string
    {
        return SafePublicHttpUrl::normalize($url);
    }
}
