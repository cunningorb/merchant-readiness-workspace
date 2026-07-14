<?php

namespace App\Services\WebsiteScan;

use App\Models\Assessment;
use App\Models\AssessmentAnswerEvidence;
use App\Models\WebsiteScan;
use Illuminate\Support\Str;

class StartWebsiteScanService
{
    public function __construct(
        private readonly WebsiteCrawler $crawler,
        private readonly WebsiteExtractionStrategyResolver $strategyResolver,
        private readonly ApplyWebsiteEvidenceToAnswersService $applyWebsiteEvidenceToAnswers,
    ) {}

    public function start(Assessment $assessment, string $url): WebsiteScan
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
        $suggestions = $this->strategyResolver->resolve()->extract($scanResult);

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
                'source_label' => 'Website scan',
                'confidence' => $suggestion['confidence'],
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

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if (! Str::startsWith($url, ['http://', 'https://'])) {
            $url = 'https://'.$url;
        }

        return $url;
    }
}
