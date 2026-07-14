<?php

namespace App\Services\WebsiteScan;

use App\Contracts\WebsiteExtractionStrategy;

/**
 * Runs the deterministic rules strategy first, then asks the LLM strategy
 * only for fields rules left missing or answered below "high" confidence.
 * When both strategies produce a value for the same catalog question and the
 * values disagree, the higher-confidence one is kept as the suggestion that
 * can become an answer; a same-confidence disagreement is a genuine conflict
 * — both candidates are returned flagged requires_confirmation so neither is
 * auto-applied (see ApplyWebsiteEvidenceToAnswersService).
 */
class HybridWebsiteExtractionStrategy implements WebsiteExtractionStrategy
{
    private const CONFIDENCE_RANK = ['low' => 0, 'medium' => 1, 'high' => 2];

    public function __construct(
        private readonly RulesWebsiteExtractionStrategy $rules,
        private readonly LlmWebsiteExtractionStrategy $llm,
    ) {}

    public function extract(WebsiteScanResult $scan): array
    {
        $ruleSuggestions = array_map(
            fn (array $suggestion): array => $this->withRulesProvenance($suggestion),
            $this->rules->extract($scan),
        );

        $rulesByKey = [];
        foreach ($ruleSuggestions as $suggestion) {
            $rulesByKey[$suggestion['question_key']] = $suggestion;
        }

        $neededFields = $this->neededFields($rulesByKey);

        if ($neededFields === []) {
            return $ruleSuggestions;
        }

        $llmSuggestions = $this->llm->extractFields($scan, $neededFields);

        return $this->merge($ruleSuggestions, $rulesByKey, $llmSuggestions);
    }

    /**
     * @param  array<string, array<string, mixed>>  $rulesByKey
     * @return array<int, string>
     */
    private function neededFields(array $rulesByKey): array
    {
        $needed = [];

        foreach (LlmWebsiteExtractionStrategy::FIELDS as $field) {
            $questionKey = LlmWebsiteExtractionStrategy::QUESTION_KEY_MAP[$field] ?? null;

            if ($questionKey === null) {
                // No rules equivalent exists for this field at all — always missing.
                $needed[] = $field;

                continue;
            }

            $existing = $rulesByKey[$questionKey] ?? null;

            if ($existing === null || $existing['confidence'] !== 'high') {
                $needed[] = $field;
            }
        }

        return $needed;
    }

    /**
     * @param  array<int, array<string, mixed>>  $ruleSuggestions
     * @param  array<string, array<string, mixed>>  $rulesByKey
     * @param  array<int, array<string, mixed>>  $llmSuggestions
     * @return array<int, array<string, mixed>>
     */
    private function merge(array $ruleSuggestions, array $rulesByKey, array $llmSuggestions): array
    {
        $merged = [];
        $handledKeys = [];

        foreach ($llmSuggestions as $llmSuggestion) {
            $questionKey = $llmSuggestion['question_key'];
            $rulesSuggestion = $rulesByKey[$questionKey] ?? null;

            if ($rulesSuggestion === null) {
                $merged[] = $llmSuggestion;

                continue;
            }

            $handledKeys[$questionKey] = true;

            [$winner, $loser, $tie] = $this->resolveConflict($rulesSuggestion, $llmSuggestion);

            if ($winner === null) {
                // Same normalized value from both sources — no real conflict.
                $merged[] = $rulesSuggestion;

                continue;
            }

            if ($tie) {
                $merged[] = $this->flagRequiresConfirmation($winner);
                $merged[] = $this->flagRequiresConfirmation($loser);

                continue;
            }

            $merged[] = $winner;
            $merged[] = $this->markSuperseded($loser, $winner['provider']);
        }

        foreach ($ruleSuggestions as $ruleSuggestion) {
            if (! isset($handledKeys[$ruleSuggestion['question_key']])) {
                $merged[] = $ruleSuggestion;
            }
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $rulesSuggestion
     * @param  array<string, mixed>  $llmSuggestion
     * @return array{0: array<string, mixed>|null, 1: array<string, mixed>|null, 2: bool}
     */
    private function resolveConflict(array $rulesSuggestion, array $llmSuggestion): array
    {
        if ($this->valuesEqual($rulesSuggestion['value'], $llmSuggestion['value'])) {
            return [null, null, false];
        }

        $rulesRank = self::CONFIDENCE_RANK[$rulesSuggestion['confidence']] ?? 0;
        $llmRank = self::CONFIDENCE_RANK[$llmSuggestion['confidence']] ?? 0;

        if ($rulesRank === $llmRank) {
            return [$rulesSuggestion, $llmSuggestion, true];
        }

        return $rulesRank > $llmRank
            ? [$rulesSuggestion, $llmSuggestion, false]
            : [$llmSuggestion, $rulesSuggestion, false];
    }

    private function valuesEqual(mixed $left, mixed $right): bool
    {
        return json_encode($left) === json_encode($right);
    }

    /**
     * @param  array<string, mixed>  $suggestion
     * @return array<string, mixed>
     */
    private function flagRequiresConfirmation(array $suggestion): array
    {
        $suggestion['requires_confirmation'] = true;

        return $suggestion;
    }

    /**
     * @param  array<string, mixed>  $suggestion
     * @return array<string, mixed>
     */
    private function markSuperseded(array $suggestion, ?string $winningProvider): array
    {
        $suggestion['metadata'] = array_merge($suggestion['metadata'] ?? [], [
            'superseded_by' => $winningProvider,
        ]);

        return $suggestion;
    }

    /**
     * @param  array<string, mixed>  $suggestion
     * @return array<string, mixed>
     */
    private function withRulesProvenance(array $suggestion): array
    {
        $suggestion['provider'] ??= 'rules';
        $suggestion['model'] ??= null;
        $suggestion['prompt_version'] ??= null;

        return $suggestion;
    }
}
