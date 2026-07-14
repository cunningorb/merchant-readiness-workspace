<?php

namespace App\Services\Ai;

use App\Contracts\RecommendationInsightGenerator;
use App\Models\Assessment;
use App\Models\Recommendation;
use App\Services\Llm\LlmClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Generates one AI executive-insight explanation for the highest-impact
 * recommendation. The recommendation itself is never decided here — it
 * comes in as an already-chosen App\Models\Recommendation from the
 * deterministic rules engine; this only explains why it matters.
 *
 * Reuses the existing LlmClient/GroqLlmClient abstraction unmodified.
 */
class RecommendationInsightService implements RecommendationInsightGenerator
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
        You are an experienced ecommerce operations consultant. Your audience is the merchant
        owner. You are explaining why ONE recommendation should be prioritized.

        Rules:
        - Never invent data. Reference only the supplied evidence.
        - Never exaggerate. Never promise savings.
        - Never mention AI.
        - Keep the explanation concise — assume the merchant has less than 30 seconds.
        - Speak in business language, not technical language.
        - If confidence is medium or low, acknowledge the uncertainty explicitly.
        - Output JSON only, matching the requested schema exactly.
        PROMPT;

    public function __construct(
        private readonly LlmClient $client,
        private readonly MerchantContextBuilder $contextBuilder,
    ) {}

    public function generate(Assessment $assessment, Recommendation $recommendation): ?RecommendationInsight
    {
        if (! config('llm.enabled', false) || ! config('ai.recommendation_insight.enabled', true)) {
            return null;
        }

        $provider = (string) config('llm.provider', 'groq');
        $model = (string) config('services.groq.model');
        $promptVersion = (string) config('ai.recommendation_insight.prompt_version', 'v1');
        $cacheKey = $this->cacheKey($assessment->id, $recommendation->id, $promptVersion, $provider, $model);

        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $this->hydrate($cached);
        }

        $insight = $this->generateFresh($assessment, $recommendation, $provider);

        if ($insight !== null) {
            Cache::put(
                $cacheKey,
                $insight->toArray(),
                now()->addHours((int) config('ai.recommendation_insight.cache_ttl_hours', 24)),
            );
        }

        return $insight;
    }

    private function generateFresh(Assessment $assessment, Recommendation $recommendation, string $provider): ?RecommendationInsight
    {
        $context = $this->contextBuilder->build($assessment, $recommendation);

        // Clamp the shared client's timeout for this call only, then restore
        // it — the 5s report-generation budget is a constraint of this
        // feature, not of the shared LlmClient abstraction, which the
        // website-scan feature configures independently.
        $originalTimeout = config('llm.timeout_seconds');
        $maxSeconds = (int) config('ai.recommendation_insight.max_generation_seconds', 4);

        try {
            config(['llm.timeout_seconds' => min((int) $originalTimeout, $maxSeconds)]);

            $decoded = $this->client->extractStructured(
                $this->buildMessages($context),
                $this->buildSchema(),
            );

            return RecommendationInsight::fromArray($decoded);
        } catch (Throwable $exception) {
            Log::warning('Recommendation insight generation unavailable; showing deterministic recommendation only.', [
                'provider' => $provider,
                'exception' => $exception::class,
            ]);

            return null;
        } finally {
            config(['llm.timeout_seconds' => $originalTimeout]);
        }
    }

    private function hydrate(array $cached): ?RecommendationInsight
    {
        try {
            return RecommendationInsight::fromArray($cached);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function cacheKey(string $assessmentId, int $recommendationId, string $promptVersion, string $provider, string $model): string
    {
        return "recommendation-insight:{$assessmentId}:{$recommendationId}:{$promptVersion}:{$provider}:{$model}";
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function buildMessages(MerchantContext $context): array
    {
        return [
            ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
            ['role' => 'user', 'content' => json_encode(['merchant_context' => $context->toArray()], JSON_THROW_ON_ERROR)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSchema(): array
    {
        $stringField = ['type' => 'string'];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'headline', 'executive_summary', 'merchant_context', 'business_reasoning',
                'priority_reason', 'implementation_notes', 'confidence', 'disclaimer',
            ],
            'properties' => [
                'headline' => $stringField,
                'executive_summary' => $stringField,
                'merchant_context' => $stringField,
                'business_reasoning' => $stringField,
                'priority_reason' => $stringField,
                'implementation_notes' => $stringField,
                'confidence' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
                'disclaimer' => $stringField,
            ],
        ];
    }
}
