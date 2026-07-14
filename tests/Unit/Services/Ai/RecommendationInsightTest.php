<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\RecommendationInsight;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecommendationInsightTest extends TestCase
{
    private function validPayload(array $overrides = []): array
    {
        return array_replace([
            'headline' => 'Exchanges are quietly costing you revenue',
            'executive_summary' => 'You process returns manually and rarely offer exchanges, which pushes customers toward refunds instead of a replacement order.',
            'merchant_context' => 'A single-warehouse apparel seller with a 17% return rate.',
            'business_reasoning' => 'Exchange-first flows keep the sale instead of losing it to a refund.',
            'priority_reason' => 'This is the highest-confidence, highest-value opportunity identified.',
            'implementation_notes' => 'Start by defaulting eligible returns into an exchange flow at checkout.',
            'confidence' => 'high',
            'disclaimer' => 'Estimate based on your answers and clearly labeled assumptions — not a promise of results.',
        ], $overrides);
    }

    public function test_builds_from_a_valid_array(): void
    {
        $insight = RecommendationInsight::fromArray($this->validPayload());

        $this->assertSame('Exchanges are quietly costing you revenue', $insight->headline);
        $this->assertSame('high', $insight->confidence);
    }

    public function test_round_trips_through_to_array(): void
    {
        $payload = $this->validPayload();
        $insight = RecommendationInsight::fromArray($payload);

        $this->assertSame($payload, $insight->toArray());
    }

    public function test_rejects_an_invalid_confidence_level(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RecommendationInsight::fromArray($this->validPayload(['confidence' => 'certain']));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function requiredFieldProvider(): array
    {
        return [
            'headline' => ['headline'],
            'executive_summary' => ['executive_summary'],
            'merchant_context' => ['merchant_context'],
            'business_reasoning' => ['business_reasoning'],
            'priority_reason' => ['priority_reason'],
            'implementation_notes' => ['implementation_notes'],
            'confidence' => ['confidence'],
            'disclaimer' => ['disclaimer'],
        ];
    }

    #[DataProvider('requiredFieldProvider')]
    public function test_rejects_a_blank_required_field(string $field): void
    {
        $this->expectException(InvalidArgumentException::class);

        RecommendationInsight::fromArray($this->validPayload([$field => '']));
    }

    #[DataProvider('requiredFieldProvider')]
    public function test_rejects_a_missing_required_field(string $field): void
    {
        $payload = $this->validPayload();
        unset($payload[$field]);

        $this->expectException(InvalidArgumentException::class);

        RecommendationInsight::fromArray($payload);
    }

    #[DataProvider('requiredFieldProvider')]
    public function test_rejects_a_non_string_field(string $field): void
    {
        $this->expectException(InvalidArgumentException::class);

        RecommendationInsight::fromArray($this->validPayload([$field => ['not', 'a', 'string']]));
    }
}
