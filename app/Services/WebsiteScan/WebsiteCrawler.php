<?php

namespace App\Services\WebsiteScan;

use App\Support\SafePublicHttpUrl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class WebsiteCrawler
{
    private const MAX_PAGES = 4;

    public function crawl(string $url): WebsiteScanResult
    {
        try {
            $pages = [];
            $response = $this->fetch($url);

            if ($response === null || ! $response->successful()) {
                return new WebsiteScanResult($url, []);
            }

            $pages[] = [
                'url' => $url,
                'html' => $response->body(),
            ];

            foreach ($this->candidateUrls($url, $response->body()) as $candidateUrl) {
                if (count($pages) >= self::MAX_PAGES) {
                    break;
                }

                $candidateResponse = $this->fetch($candidateUrl);

                if ($candidateResponse?->successful()) {
                    $pages[] = [
                        'url' => $candidateUrl,
                        'html' => $candidateResponse->body(),
                    ];
                }
            }

            return new WebsiteScanResult($url, $pages);
        } catch (Throwable) {
            return new WebsiteScanResult($url, []);
        }
    }

    private function fetch(string $url): mixed
    {
        if (! SafePublicHttpUrl::isAllowed($url)) {
            return null;
        }

        return Http::timeout(5)
            ->connectTimeout(3)
            ->withHeaders([
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'User-Agent' => 'Mozilla/5.0 (compatible; MerchantReadinessBot/1.0; +https://merchant-readiness-workspace.test)',
            ])
            ->get($url);
    }

    /**
     * @return array<int, string>
     */
    private function candidateUrls(string $baseUrl, string $html): array
    {
        $base = parse_url($baseUrl);
        $scheme = $base['scheme'] ?? 'https';
        $host = $base['host'] ?? null;

        if (! $host) {
            return [];
        }

        preg_match_all('/<a\s+[^>]*href=["\']([^"\']+)["\']/i', $html, $matches);

        return collect($matches[1] ?? [])
            ->map(fn (string $href): ?string => $this->absoluteUrl($href, $scheme, $host))
            ->filter()
            ->filter(fn (string $candidateUrl): bool => $this->isSameHost($candidateUrl, $host))
            ->unique()
            ->filter(fn (string $candidateUrl): bool => $this->isUsefulCandidate($candidateUrl))
            ->sortBy(fn (string $candidateUrl): int => $this->candidatePriority($candidateUrl))
            ->values()
            ->all();
    }

    private function isUsefulCandidate(string $url): bool
    {
        $path = strtolower(parse_url($url, PHP_URL_PATH) ?? '');

        return Str::contains($path, [
            'return',
            'returns',
            'refund',
            'exchange',
            'policy',
            'policies',
            'contact',
            'support',
            'help',
            'faq',
            'about',
        ]);
    }

    private function candidatePriority(string $url): int
    {
        $path = strtolower(parse_url($url, PHP_URL_PATH) ?? '');

        return match (true) {
            Str::contains($path, ['return', 'returns', 'refund', 'exchange']) => 0,
            Str::contains($path, ['policy', 'policies', 'faq']) => 1,
            Str::contains($path, ['contact', 'support', 'help']) => 2,
            default => 3,
        };
    }

    private function absoluteUrl(string $href, string $scheme, string $host): ?string
    {
        $href = trim($href);

        if ($href === '' || Str::startsWith($href, ['#', 'mailto:', 'tel:', 'javascript:'])) {
            return null;
        }

        if (Str::startsWith($href, '//')) {
            return $scheme.':'.$href;
        }

        if (Str::startsWith($href, ['http://', 'https://'])) {
            return $href;
        }

        return $scheme.'://'.$host.'/'.ltrim($href, '/');
    }

    private function isSameHost(string $url, string $host): bool
    {
        return parse_url($url, PHP_URL_HOST) === $host;
    }
}
