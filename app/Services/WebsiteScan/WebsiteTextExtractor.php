<?php

namespace App\Services\WebsiteScan;

/**
 * Reduces crawled HTML pages to plain, visible text blocks for the LLM
 * strategy. Deliberately conservative: strips script/style/nav/header/footer
 * chrome and common cookie-banner containers by tag and by a small set of
 * class/id substrings, but this is a best-effort heuristic, not a full
 * boilerplate-removal pipeline — some site chrome will still leak through.
 */
class WebsiteTextExtractor
{
    private const BOILERPLATE_TAGS = ['script', 'style', 'noscript', 'svg', 'template', 'nav', 'header', 'footer'];

    private const BANNER_MARKERS = ['cookie', 'consent', 'gdpr', 'newsletter-popup'];

    /**
     * @return array<int, array{url: string, text: string}>
     */
    public function extractBlocks(WebsiteScanResult $scan, int $maxPages, int $maxCharacters): array
    {
        $blocks = [];
        $remaining = max(0, $maxCharacters);

        foreach (array_slice($scan->pages, 0, max(0, $maxPages)) as $page) {
            if ($remaining <= 0) {
                break;
            }

            $text = $this->toVisibleText($page['html']);

            if ($text === '') {
                continue;
            }

            $text = mb_substr($text, 0, $remaining);
            $remaining -= mb_strlen($text);

            $blocks[] = ['url' => $page['url'], 'text' => $text];
        }

        return $blocks;
    }

    private function toVisibleText(string $html): string
    {
        foreach (self::BOILERPLATE_TAGS as $tag) {
            $html = (string) preg_replace("/<{$tag}\\b[^>]*>.*?<\\/{$tag}>/is", ' ', $html);
        }

        $html = (string) preg_replace('/<!--.*?-->/s', ' ', $html);
        $html = $this->stripBannerContainers($html);

        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    private function stripBannerContainers(string $html): string
    {
        $markerPattern = implode('|', array_map('preg_quote', self::BANNER_MARKERS));

        return (string) preg_replace(
            '/<(div|section|aside)\b[^>]*(?:class|id)=["\'][^"\']*(?:'.$markerPattern.')[^"\']*["\'][^>]*>.*?<\/\1>/is',
            ' ',
            $html,
        );
    }
}
