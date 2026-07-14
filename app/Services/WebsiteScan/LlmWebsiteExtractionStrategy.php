<?php

namespace App\Services\WebsiteScan;

use App\Contracts\WebsiteExtractionStrategy;
use App\Services\Llm\Exceptions\LlmExtractionException;
use App\Services\Llm\LlmClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Optional LLM-assisted extraction. Never throws to its caller: any provider
 * failure (disabled, timeout, bad status, invalid JSON, or evidence that
 * fails verification) degrades to an empty suggestion list so the website
 * scan always completes. Only ever receives WebsiteScanResult — the scraped
 * page HTML — so it structurally cannot see merchant answers, CSV data, or
 * contact details.
 */
class LlmWebsiteExtractionStrategy implements WebsiteExtractionStrategy
{
    public const FIELDS = [
        'return_window_days',
        'exchanges_offered',
        'exchange_incentive',
        'restocking_fee',
        'final_sale_rules',
        'policy_clarity',
        'international_returns',
        'product_category_hints',
    ];

    /**
     * Fields with a direct scoring-question equivalent. The rest have no
     * catalog question and are stored as evidence-only insights under a
     * synthetic "website_insight.*" key, so they're displayed but never
     * turned into an AssessmentAnswer.
     */
    public const QUESTION_KEY_MAP = [
        'return_window_days' => 'return_policy.window_days',
        'exchanges_offered' => 'exchanges.offered',
        'policy_clarity' => 'return_policy.policy_clarity',
    ];

    public function __construct(
        private readonly LlmClient $client,
        private readonly WebsiteTextExtractor $textExtractor,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function extract(WebsiteScanResult $scan): array
    {
        return $this->extractFields($scan, self::FIELDS);
    }

    /**
     * Requests only the given subset of FIELDS — used by the hybrid strategy
     * so a field the rules strategy already answered with high confidence is
     * never even sent to the provider.
     *
     * @param  array<int, string>  $fields
     * @return array<int, array<string, mixed>>
     */
    public function extractFields(WebsiteScanResult $scan, array $fields): array
    {
        $fields = array_values(array_intersect(self::FIELDS, $fields));

        if ($fields === [] || ! config('llm.enabled', false)) {
            return [];
        }

        $blocks = $this->textExtractor->extractBlocks(
            $scan,
            maxPages: (int) config('llm.max_pages', 6),
            maxCharacters: (int) config('llm.max_input_characters', 30000),
        );

        if ($blocks === []) {
            return [];
        }

        $decoded = $this->fetchDecoded($scan->url, $blocks, $fields);

        if ($decoded === null) {
            return [];
        }

        return $this->toSuggestions($decoded, $fields, $blocks);
    }

    /**
     * @param  array<int, array{url: string, text: string}>  $blocks
     * @param  array<int, string>  $fields
     * @return array<string, mixed>|null
     */
    private function fetchDecoded(string $scanUrl, array $blocks, array $fields): ?array
    {
        $cacheKey = $this->cacheKey($scanUrl, $blocks, $fields);
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $decoded = $this->client->extractStructured(
                $this->buildMessages($blocks, $fields),
                $this->buildSchema($fields),
            );
        } catch (LlmExtractionException|Throwable $exception) {
            Log::warning('Website LLM extraction unavailable; continuing with rules-only evidence.', [
                'provider' => config('llm.provider'),
                'exception' => $exception::class,
                'reason' => $exception->getMessage(),
            ]);

            return null;
        }

        Cache::put($cacheKey, $decoded, now()->addHours(6));

        return $decoded;
    }

    /**
     * @param  array<int, array{url: string, text: string}>  $blocks
     * @param  array<int, string>  $fields
     */
    private function cacheKey(string $scanUrl, array $blocks, array $fields): string
    {
        $contentHash = hash('sha256', implode("\n", array_map(
            fn (array $block): string => $block['url'].'|'.$block['text'],
            $blocks,
        )));

        return 'llm-extraction:'.hash('sha256', implode('|', [
            $scanUrl,
            $contentHash,
            (string) config('services.groq.model'),
            (string) config('llm.prompt_version', 'v1'),
            implode(',', $fields),
        ]));
    }

    /**
     * @param  array<int, array{url: string, text: string}>  $blocks
     * @param  array<int, string>  $fields
     * @return array<int, array{role: string, content: string}>
     */
    private function buildMessages(array $blocks, array $fields): array
    {
        $fieldList = implode(', ', $fields);

        $system = 'You extract only publicly stated returns-policy facts from the supplied source blocks of a '
            .'merchant\'s own public website. Extract only these fields: '.$fieldList.'. Do not infer unsupported '
            .'facts, and never use outside knowledge about this merchant. When evidence is absent, return null for '
            .'value, evidence_quote, source_url, and explanation, and use confidence "low". Every non-null value '
            .'must be backed by a verbatim evidence_quote copied exactly from its matching source block, and '
            .'source_url must be the url of that block. Respond with a single JSON object only, with one key per '
            .'requested field — no prose, no markdown code fences.';

        $sourceBlocks = array_map(fn (array $block): array => [
            'url' => $block['url'],
            'text' => $block['text'],
        ], $blocks);

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => json_encode(['pages' => $sourceBlocks], JSON_THROW_ON_ERROR)],
        ];
    }

    /**
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    private function buildSchema(array $fields): array
    {
        $properties = [];

        foreach ($fields as $field) {
            $properties[$field] = $this->fieldSchema($field);
        }

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => $fields,
            'properties' => $properties,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldSchema(string $field): array
    {
        $valueSchema = match ($field) {
            'return_window_days' => ['type' => ['integer', 'null']],
            'exchanges_offered' => ['type' => ['boolean', 'null']],
            'policy_clarity' => ['type' => ['string', 'null'], 'enum' => ['clear', 'partially_clear', 'unclear', null]],
            'product_category_hints' => ['type' => ['array', 'null'], 'items' => ['type' => 'string']],
            default => ['type' => ['string', 'null']],
        };

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['value', 'confidence', 'evidence_quote', 'source_url', 'explanation'],
            'properties' => [
                'value' => $valueSchema,
                'confidence' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
                'evidence_quote' => ['type' => ['string', 'null']],
                'source_url' => ['type' => ['string', 'null']],
                'explanation' => ['type' => ['string', 'null']],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @param  array<int, string>  $fields
     * @param  array<int, array{url: string, text: string}>  $blocks
     * @return array<int, array<string, mixed>>
     */
    private function toSuggestions(array $decoded, array $fields, array $blocks): array
    {
        $blocksByUrl = [];
        foreach ($blocks as $block) {
            $blocksByUrl[$block['url']] = $block['text'];
        }

        $suggestions = [];

        foreach ($fields as $field) {
            $entry = $decoded[$field] ?? null;

            if (! is_array($entry) || ($entry['value'] ?? null) === null) {
                continue;
            }

            $confidence = in_array($entry['confidence'] ?? null, ['high', 'medium', 'low'], true)
                ? $entry['confidence']
                : 'low';
            $sourceUrl = is_string($entry['source_url'] ?? null) ? $entry['source_url'] : null;
            $evidenceQuote = is_string($entry['evidence_quote'] ?? null) ? trim($entry['evidence_quote']) : null;

            if ($sourceUrl === null || $evidenceQuote === null || $evidenceQuote === '') {
                continue;
            }

            $sourceText = $blocksByUrl[$sourceUrl] ?? null;

            if ($sourceText === null || ! $this->containsNormalized($sourceText, $evidenceQuote)) {
                continue;
            }

            $value = $this->normalizeValue($field, $entry['value']);

            if ($value === null) {
                continue;
            }

            $suggestions[] = [
                'question_key' => self::QUESTION_KEY_MAP[$field] ?? "website_insight.{$field}",
                'value' => $value,
                'confidence' => $confidence,
                'evidence_url' => $sourceUrl,
                'evidence_snippet' => $evidenceQuote,
                'metadata' => [
                    'llm_field' => $field,
                    'explanation' => is_string($entry['explanation'] ?? null) ? $entry['explanation'] : null,
                ],
                'provider' => (string) config('llm.provider', 'groq'),
                'model' => (string) config('services.groq.model'),
                'prompt_version' => (string) config('llm.prompt_version', 'v1'),
            ];
        }

        return $suggestions;
    }

    private function containsNormalized(string $haystack, string $needle): bool
    {
        return str_contains($this->normalize($haystack), $this->normalize($needle));
    }

    private function normalize(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', strtolower($text)));
    }

    private function normalizeValue(string $field, mixed $value): mixed
    {
        return match ($field) {
            'return_window_days' => is_int($value) ? $this->windowBand($value) : null,
            'policy_clarity' => $this->policyClarityLabel($value),
            default => $value,
        };
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

    private function policyClarityLabel(mixed $value): ?string
    {
        return match ($value) {
            'clear' => 'Detailed policy page',
            'partially_clear' => 'Contextual policy by product/order',
            'unclear' => 'Basic FAQ',
            default => null,
        };
    }
}
