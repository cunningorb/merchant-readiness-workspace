<?php

namespace App\Contracts;

use App\Services\WebsiteScan\WebsiteScanResult;

interface WebsiteExtractionStrategy
{
    /**
     * @return array<int, array{question_key: string, value: mixed, confidence: string, evidence_url?: string|null, evidence_snippet?: string|null, metadata?: array<string, mixed>}>
     */
    public function extract(WebsiteScanResult $scan): array;
}
