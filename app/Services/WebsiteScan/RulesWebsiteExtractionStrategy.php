<?php

namespace App\Services\WebsiteScan;

use App\Contracts\WebsiteExtractionStrategy;
use Illuminate\Support\Str;

class RulesWebsiteExtractionStrategy implements WebsiteExtractionStrategy
{
    public function extract(WebsiteScanResult $scan): array
    {
        $suggestions = [];

        foreach ($scan->pages as $page) {
            $html = $page['html'];
            $text = $this->plainText($html);

            if ($companyName = $this->companyName($html)) {
                $suggestions[] = $this->suggestion(
                    questionKey: 'business.company_name',
                    value: $companyName,
                    confidence: 'medium',
                    url: $page['url'],
                    snippet: $companyName,
                    metadata: ['rule' => 'html_title_or_meta']
                );
            }

            if ($platform = $this->platform($html)) {
                $suggestions[] = $this->suggestion(
                    questionKey: 'platform.ecommerce_platform',
                    value: $platform,
                    confidence: 'medium',
                    url: $page['url'],
                    snippet: $platform,
                    metadata: ['rule' => 'platform_marker']
                );
            }

            $returnTools = $this->returnTools($html, $text);
            $suggestions[] = $this->suggestion(
                questionKey: 'platform.return_tools',
                value: $returnTools['value'],
                confidence: $returnTools['confidence'],
                url: $page['url'],
                snippet: $returnTools['snippet'] ? $this->snippetAround($text, $returnTools['snippet']) : 'No known returns tooling marker found.',
                metadata: ['rule' => $returnTools['rule']]
            );

            if ($email = $this->email($text)) {
                $suggestions[] = $this->suggestion(
                    questionKey: 'business.contact_email',
                    value: $email,
                    confidence: 'high',
                    url: $page['url'],
                    snippet: $this->snippetAround($text, $email),
                    metadata: ['rule' => 'plain_text_email']
                );
            }

            if ($window = $this->returnWindow($text)) {
                $suggestions[] = $this->suggestion(
                    questionKey: 'return_policy.window_days',
                    value: $this->windowBand($window),
                    confidence: 'high',
                    url: $page['url'],
                    snippet: $this->snippetAround($text, (string) $window),
                    metadata: ['rule' => 'return_window_days', 'days' => $window]
                );
            }

            if ($policyClarity = $this->policyClarity($text, $page['url'])) {
                $suggestions[] = $this->suggestion(
                    questionKey: 'return_policy.policy_clarity',
                    value: $policyClarity,
                    confidence: $policyClarity === 'Contextual policy by product/order' ? 'medium' : 'high',
                    url: $page['url'],
                    snippet: $this->snippetAround($text, 'return'),
                    metadata: ['rule' => 'policy_page_clarity']
                );
            }

            if ($this->mentionsExchanges($text)) {
                $suggestions[] = $this->suggestion(
                    questionKey: 'exchanges.offered',
                    value: true,
                    confidence: 'medium',
                    url: $page['url'],
                    snippet: $this->snippetAround($text, 'exchange'),
                    metadata: ['rule' => 'exchange_keyword']
                );
            }
        }

        return collect($suggestions)
            ->sortBy(fn (array $suggestion): int => $this->suggestionPriority($suggestion))
            ->unique('question_key')
            ->values()
            ->all();
    }

    private function suggestionPriority(array $suggestion): int
    {
        if (($suggestion['question_key'] ?? null) === 'return_policy.policy_clarity') {
            return match ($suggestion['value'] ?? null) {
                'Detailed policy page' => 0,
                'Contextual policy by product/order' => 1,
                'Basic FAQ' => 2,
                default => 3,
            };
        }

        if (($suggestion['metadata']['rule'] ?? null) === 'return_tooling_default_custom') {
            return 3;
        }

        return match ($suggestion['confidence'] ?? null) {
            'high' => 0,
            'medium' => 1,
            'low' => 2,
            default => 3,
        };
    }

    private function plainText(string $html): string
    {
        $html = (string) preg_replace('/<(script|style|noscript)\b[^>]*>.*?<\/\1>/is', ' ', $html);

        return $this->cleanText(strip_tags($html));
    }

    private function companyName(string $html): ?string
    {
        if (preg_match('/<meta[^>]+property=["\']og:site_name["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $match)) {
            return $this->cleanText($match[1]);
        }

        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $match)) {
            return $this->cleanText(Str::before(strip_tags($match[1]), '|'));
        }

        return null;
    }

    private function platform(string $html): ?string
    {
        $lower = strtolower($html);

        if (str_contains($lower, 'cdn.shopify.com') || str_contains($lower, 'shopify')) {
            return 'Shopify';
        }

        if (str_contains($lower, 'woocommerce') || str_contains($lower, 'wp-content/plugins/woocommerce')) {
            return 'WooCommerce';
        }

        return null;
    }

    /**
     * @return array{value: string, confidence: string, snippet: string|null, rule: string}
     */
    private function returnTools(string $html, string $text): array
    {
        $haystack = strtolower($html.' '.$text);

        foreach ([
            'email us to start a return',
            'email us for a return',
            'email us for returns',
            'contact us to start a return',
            'contact us for a return',
        ] as $marker) {
            if (str_contains($haystack, $marker)) {
                return [
                    'value' => 'Email/spreadsheets',
                    'confidence' => 'medium',
                    'snippet' => $marker,
                    'rule' => 'manual_email_return_marker',
                ];
            }
        }

        foreach ([
            'support ticket',
            'help desk',
            'helpdesk',
            'contact customer service',
            'contact support',
        ] as $marker) {
            if (str_contains($haystack, $marker)) {
                return [
                    'value' => 'Helpdesk workflow',
                    'confidence' => 'medium',
                    'snippet' => $marker,
                    'rule' => 'helpdesk_return_marker',
                ];
            }
        }

        foreach ([
            'loopreturns.com',
            'happyreturns.com',
            'returnly.com',
            'aftership.com/returns',
            'narvar.com',
            'redo returns',
            'richcommerce',
            'returnscenter',
            'return center',
            'returns portal',
            'start a return',
            'manage your return',
        ] as $marker) {
            if (str_contains($haystack, $marker)) {
                return [
                    'value' => 'Returns app',
                    'confidence' => 'high',
                    'snippet' => $marker,
                    'rule' => 'returns_app_marker',
                ];
            }
        }

        return [
            'value' => 'Custom automation',
            'confidence' => 'low',
            'snippet' => null,
            'rule' => 'return_tooling_default_custom',
        ];
    }

    private function returnWindow(string $text): ?int
    {
        if (preg_match('/(?:within|for|in)\s+(\d{1,3})\s+days?/i', $text, $match)) {
            return (int) $match[1];
        }

        if (preg_match('/(\d{1,3})[- ]day\s+(?:return|refund|exchange)/i', $text, $match)) {
            return (int) $match[1];
        }

        return null;
    }

    private function email(string $text): ?string
    {
        if (! preg_match('/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/i', $text, $match)) {
            return null;
        }

        return $this->normalizeEmail($match[0]);
    }

    private function policyClarity(string $text, string $url): ?string
    {
        $lowerText = strtolower($text);
        $path = strtolower(parse_url($url, PHP_URL_PATH) ?? '');

        if (! Str::contains($lowerText, ['return', 'refund', 'exchange'])) {
            return null;
        }

        if (Str::contains($lowerText, [
            'varies by product',
            'varies by item',
            'depending on the product',
            'depending on your order',
            'product-specific',
            'item-specific',
        ])) {
            return 'Contextual policy by product/order';
        }

        if (Str::contains($path, ['return', 'refund', 'exchange', 'policies', 'policy'])
            || Str::contains($lowerText, ['return policy', 'refund policy', 'returns policy'])) {
            return 'Detailed policy page';
        }

        return 'Basic FAQ';
    }

    private function normalizeEmail(string $email): string
    {
        [$localPart, $domain] = explode('@', strtolower($email), 2);

        $localPart = preg_replace('/^\d{3}[-.\s]?\d{3}[-.\s]?\d{4}/', '', $localPart) ?: $localPart;

        return $localPart.'@'.$domain;
    }

    private function windowBand(int $days): string
    {
        return match (true) {
            $days <= 14 => '14 days or less',
            $days <= 30 => '15-30 days',
            $days <= 60 => '31-60 days',
            default => 'More than 60 days',
        };
    }

    private function mentionsExchanges(string $text): bool
    {
        return str_contains(strtolower($text), 'exchange');
    }

    private function snippetAround(string $text, string $needle): string
    {
        $position = stripos($text, $needle);

        if ($position === false) {
            return Str::limit($text, 180);
        }

        return $this->cleanText(Str::limit(substr($text, max(0, $position - 80), 220), 180));
    }

    private function cleanText(string $text): string
    {
        return trim((string) preg_replace(
            '/\s+/',
            ' ',
            html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        ));
    }

    /**
     * @return array{question_key: string, value: mixed, confidence: string, evidence_url: string, evidence_snippet: string, metadata: array<string, mixed>}
     */
    private function suggestion(string $questionKey, mixed $value, string $confidence, string $url, string $snippet, array $metadata): array
    {
        return [
            'question_key' => $questionKey,
            'value' => is_string($value) ? $this->cleanText($value) : $value,
            'confidence' => $confidence,
            'evidence_url' => $url,
            'evidence_snippet' => $this->cleanText($snippet),
            'metadata' => $metadata,
        ];
    }
}
