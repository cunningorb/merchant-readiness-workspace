<?php

namespace App\Services\Ai;

use InvalidArgumentException;

/**
 * A merchant-specific executive explanation for one deterministic
 * recommendation. Every field is required and non-blank — a partially
 * malformed LLM response is treated as a failed generation (see
 * RecommendationInsightService), not shown with gaps.
 */
final class RecommendationInsight
{
    private const CONFIDENCE_LEVELS = ['high', 'medium', 'low'];

    public function __construct(
        public readonly string $headline,
        public readonly string $executiveSummary,
        public readonly string $merchantContext,
        public readonly string $businessReasoning,
        public readonly string $priorityReason,
        public readonly string $implementationNotes,
        public readonly string $confidence,
        public readonly string $disclaimer,
    ) {
        if (! in_array($confidence, self::CONFIDENCE_LEVELS, true)) {
            throw new InvalidArgumentException("Invalid confidence level: '{$confidence}'.");
        }

        foreach ([
            'headline' => $headline,
            'executive_summary' => $executiveSummary,
            'merchant_context' => $merchantContext,
            'business_reasoning' => $businessReasoning,
            'priority_reason' => $priorityReason,
            'implementation_notes' => $implementationNotes,
            'disclaimer' => $disclaimer,
        ] as $field => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException("'{$field}' cannot be blank.");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            headline: self::stringField($data, 'headline'),
            executiveSummary: self::stringField($data, 'executive_summary'),
            merchantContext: self::stringField($data, 'merchant_context'),
            businessReasoning: self::stringField($data, 'business_reasoning'),
            priorityReason: self::stringField($data, 'priority_reason'),
            implementationNotes: self::stringField($data, 'implementation_notes'),
            confidence: self::stringField($data, 'confidence'),
            disclaimer: self::stringField($data, 'disclaimer'),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function stringField(array $data, string $key): string
    {
        if (! array_key_exists($key, $data)) {
            throw new InvalidArgumentException("'{$key}' is required.");
        }

        $value = $data[$key];

        if (! is_string($value)) {
            throw new InvalidArgumentException("'{$key}' must be a string.");
        }

        return $value;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'headline' => $this->headline,
            'executive_summary' => $this->executiveSummary,
            'merchant_context' => $this->merchantContext,
            'business_reasoning' => $this->businessReasoning,
            'priority_reason' => $this->priorityReason,
            'implementation_notes' => $this->implementationNotes,
            'confidence' => $this->confidence,
            'disclaimer' => $this->disclaimer,
        ];
    }
}
