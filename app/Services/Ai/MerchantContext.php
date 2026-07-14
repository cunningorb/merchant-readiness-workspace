<?php

namespace App\Services\Ai;

/**
 * Structured, PII-free JSON payload sent to the LLM. Deliberately excludes
 * contact_email/contact_name, raw CSV rows, and raw website HTML — only
 * pre-aggregated scalars and already-cleaned short evidence snippets cross
 * this boundary. See MerchantContextBuilder for how each field is sourced.
 */
final class MerchantContext
{
    public function __construct(
        public readonly ?string $companyName,
        public readonly ?string $industry,
        public readonly ?string $website,
        public readonly ?string $platform,
        public readonly ?int $readinessScore,
        /** @var array<int, array<string, mixed>> */
        public readonly array $topOpportunities,
        /** @var array<string, mixed> */
        public readonly array $topRecommendation,
        public readonly ?string $manualHours,
        public readonly ?float $estimatedReturnRate,
        public readonly ?float $estimatedExchangeRate,
        public readonly ?float $estimatedOrderVolume,
        public readonly ?float $estimatedAov,
        public readonly ?string $catalogComplexity,
        public readonly ?int $policyScore,
        public readonly ?int $automationScore,
        public readonly ?int $exchangeScore,
        public readonly ?int $riskScore,
        /** @var array<int, array<string, mixed>> */
        public readonly array $peerBenchmarkSummary,
        /** @var array<string, mixed>|null */
        public readonly ?array $websiteScanSummary,
        /** @var array<int, array<string, mixed>> */
        public readonly array $evidenceSummaries,
        /** @var array<string, mixed>|null */
        public readonly ?array $opportunityEstimate,
        public readonly ?string $confidence,
        /** @var array<string, mixed>|null */
        public readonly ?array $publicContext,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'company_name' => $this->companyName,
            'industry' => $this->industry,
            'website' => $this->website,
            'platform' => $this->platform,
            'readiness_score' => $this->readinessScore,
            'top_opportunities' => $this->topOpportunities,
            'top_recommendation' => $this->topRecommendation,
            'manual_hours' => $this->manualHours,
            'estimated_return_rate' => $this->estimatedReturnRate,
            'estimated_exchange_rate' => $this->estimatedExchangeRate,
            'estimated_order_volume' => $this->estimatedOrderVolume,
            'estimated_aov' => $this->estimatedAov,
            'catalog_complexity' => $this->catalogComplexity,
            'policy_score' => $this->policyScore,
            'automation_score' => $this->automationScore,
            'exchange_score' => $this->exchangeScore,
            'risk_score' => $this->riskScore,
            'peer_benchmark_summary' => $this->peerBenchmarkSummary,
            'website_scan_summary' => $this->websiteScanSummary,
            'evidence_summaries' => $this->evidenceSummaries,
            'opportunity_estimate' => $this->opportunityEstimate,
            'confidence' => $this->confidence,
            'public_context' => $this->publicContext,
        ];
    }
}
