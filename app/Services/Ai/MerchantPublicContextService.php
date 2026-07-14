<?php

namespace App\Services\Ai;

use App\Support\SafePublicHttpUrl;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Lightweight, best-effort public-company enrichment: one homepage GET for
 * title/meta description, plus a trivial static name→ticker mapping for a
 * handful of well-known public retailers. Deliberately not a real financial
 * data integration — no Yahoo Finance, no SEC, no API keys — so market cap
 * is never populated (a hardcoded number would be stale the moment it's
 * written, which fails "never invent data" as badly as omitting it fails
 * completeness). Any failure returns null; this must never block a report.
 */
class MerchantPublicContextService
{
    /**
     * @var array<string, array{ticker: string, sector: string, industry: string}>
     */
    private const KNOWN_PUBLIC_COMPANIES = [
        'nike' => ['ticker' => 'NKE', 'sector' => 'Consumer Discretionary', 'industry' => 'Footwear & Apparel'],
        'lululemon' => ['ticker' => 'LULU', 'sector' => 'Consumer Discretionary', 'industry' => 'Apparel Retail'],
        "levi's" => ['ticker' => 'LEVI', 'sector' => 'Consumer Discretionary', 'industry' => 'Apparel Retail'],
        'crocs' => ['ticker' => 'CROX', 'sector' => 'Consumer Discretionary', 'industry' => 'Footwear'],
        'wayfair' => ['ticker' => 'W', 'sector' => 'Consumer Discretionary', 'industry' => 'Online Retail'],
        'chewy' => ['ticker' => 'CHWY', 'sector' => 'Consumer Discretionary', 'industry' => 'Online Retail'],
        'etsy' => ['ticker' => 'ETSY', 'sector' => 'Consumer Discretionary', 'industry' => 'Online Retail'],
        'stitch fix' => ['ticker' => 'SFIX', 'sector' => 'Consumer Discretionary', 'industry' => 'Apparel Retail'],
        'warby parker' => ['ticker' => 'WRBY', 'sector' => 'Consumer Discretionary', 'industry' => 'Specialty Retail'],
        'allbirds' => ['ticker' => 'BIRD', 'sector' => 'Consumer Discretionary', 'industry' => 'Footwear & Apparel'],
    ];

    /**
     * @return array{title: ?string, meta_description: ?string, is_public: bool, ticker: ?string, sector: ?string, industry: ?string, market_cap: null}|null
     */
    public function lookup(string $website): ?array
    {
        try {
            $url = SafePublicHttpUrl::normalize($website);

            if (! SafePublicHttpUrl::isAllowed($url)) {
                return null;
            }

            $response = Http::timeout(4)->connectTimeout(2)->get($url);

            if (! $response->successful()) {
                return null;
            }

            $html = $response->body();
            $title = $this->title($html);
            $match = $this->matchKnownPublicCompany($title);

            return [
                'title' => $title,
                'meta_description' => $this->metaDescription($html),
                'is_public' => $match !== null,
                'ticker' => $match['ticker'] ?? null,
                'sector' => $match['sector'] ?? null,
                'industry' => $match['industry'] ?? null,
                'market_cap' => null,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private function title(string $html): ?string
    {
        if (! preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $match)) {
            return null;
        }

        $title = trim(html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $title !== '' ? $title : null;
    }

    private function metaDescription(string $html): ?string
    {
        if (! preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\']/i', $html, $match)) {
            return null;
        }

        $description = trim(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $description !== '' ? $description : null;
    }

    /**
     * @return array{ticker: string, sector: string, industry: string}|null
     */
    private function matchKnownPublicCompany(?string $title): ?array
    {
        if ($title === null) {
            return null;
        }

        $normalized = strtolower($title);

        foreach (self::KNOWN_PUBLIC_COMPANIES as $name => $info) {
            if (str_contains($normalized, $name)) {
                return $info;
            }
        }

        return null;
    }
}
