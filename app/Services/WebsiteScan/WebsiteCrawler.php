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

            $candidateUrls = collect($this->candidateUrls($url, $response->body()))
                ->merge($this->sitemapCandidateUrls($url))
                ->unique()
                ->sortBy(fn (string $candidateUrl): int => $this->candidatePriority($candidateUrl))
                ->values()
                ->all();

            foreach ($candidateUrls as $candidateUrl) {
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
        preg_match_all('/(?:window\.)?location(?:\.href)?\s*=\s*["\']([^"\']+)["\']/i', $html, $scriptRedirects);
        preg_match_all('/<meta[^>]+http-equiv=["\']refresh["\'][^>]+content=["\'][^"\']*url=([^"\']+)["\']/i', $html, $metaRedirects);

        $redirectUrls = collect($scriptRedirects[1] ?? [])
            ->merge($metaRedirects[1] ?? [])
            ->map(fn (string $href): ?string => $this->absoluteUrl($href, $scheme, $host))
            ->filter()
            ->filter(fn (string $candidateUrl): bool => $this->isSameHost($candidateUrl, $host))
            ->unique()
            ->values();

        $linkUrls = collect($matches[1] ?? [])
            ->map(fn (string $href): ?string => $this->absoluteUrl($href, $scheme, $host))
            ->filter()
            ->filter(fn (string $candidateUrl): bool => $this->isSameHost($candidateUrl, $host))
            ->unique()
            ->filter(fn (string $candidateUrl): bool => $this->isUsefulCandidate($candidateUrl))
            ->sortBy(fn (string $candidateUrl): int => $this->candidatePriority($candidateUrl))
            ->values();

        return $redirectUrls
            ->merge($linkUrls)
            ->unique()
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

    /**
     * @return array<int, string>
     */
    private function sitemapCandidateUrls(string $baseUrl): array
    {
        $base = parse_url($baseUrl);
        $scheme = $base['scheme'] ?? 'https';
        $host = $base['host'] ?? null;

        if (! $host) {
            return [];
        }

        return collect($this->sitemapUrls($scheme, $host))
            ->flatMap(fn (string $sitemapUrl): array => $this->urlsFromSitemap($sitemapUrl, $host))
            ->filter(fn (string $candidateUrl): bool => $this->isSameHost($candidateUrl, $host))
            ->filter(fn (string $candidateUrl): bool => $this->isUsefulCandidate($candidateUrl))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function sitemapUrls(string $scheme, string $host): array
    {
        $defaultSitemap = $scheme.'://'.$host.'/sitemap.xml';
        $robotsUrl = $scheme.'://'.$host.'/robots.txt';
        $robotsResponse = $this->fetch($robotsUrl);

        if (! $robotsResponse?->successful()) {
            return [$defaultSitemap];
        }

        preg_match_all('/^\s*Sitemap:\s*(\S+)\s*$/mi', $robotsResponse->body(), $matches);

        return collect($matches[1] ?? [])
            ->filter(fn (string $url): bool => $this->isSameHost($url, $host))
            ->push($defaultSitemap)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function urlsFromSitemap(string $sitemapUrl, string $host): array
    {
        $response = $this->fetch($sitemapUrl);

        if (! $response?->successful()) {
            return [];
        }

        preg_match_all('/<loc>\s*([^<]+)\s*<\/loc>/i', $response->body(), $matches);

        return collect($matches[1] ?? [])
            ->map(fn (string $url): string => html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            ->filter(fn (string $url): bool => $this->isSameHost($url, $host))
            ->values()
            ->all();
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
