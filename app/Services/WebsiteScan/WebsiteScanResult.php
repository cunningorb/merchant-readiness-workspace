<?php

namespace App\Services\WebsiteScan;

readonly class WebsiteScanResult
{
    /**
     * @param  array<int, array{url: string, html: string}>  $pages
     */
    public function __construct(
        public string $url,
        public array $pages,
    ) {}
}
