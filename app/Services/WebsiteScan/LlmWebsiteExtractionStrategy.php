<?php

namespace App\Services\WebsiteScan;

use App\Contracts\WebsiteExtractionStrategy;

class LlmWebsiteExtractionStrategy implements WebsiteExtractionStrategy
{
    public function extract(WebsiteScanResult $scan): array
    {
        return [];
    }
}
