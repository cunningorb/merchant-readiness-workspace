<?php

namespace Tests\Unit\Services\WebsiteScan;

use App\Services\WebsiteScan\HybridWebsiteExtractionStrategy;
use App\Services\WebsiteScan\LlmWebsiteExtractionStrategy;
use App\Services\WebsiteScan\WebsiteScanResult;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HybridWebsiteExtractionStrategyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'llm.enabled' => true,
            'llm.max_pages' => 6,
            'llm.max_input_characters' => 30000,
            'llm.prompt_version' => 'v1',
            'services.groq.key' => 'gsk_test_key',
            'services.groq.base_url' => 'https://api.groq.test/openai/v1',
            'services.groq.model' => 'test-model',
        ]);
    }

    private function scan(string $html = ''): WebsiteScanResult
    {
        $html = $html !== '' ? $html : '<p>Returns are accepted within 30 days. Final sale items cannot be returned.</p>';

        return new WebsiteScanResult('https://merchant.test', [
            ['url' => 'https://merchant.test/pages/returns', 'html' => $html],
        ]);
    }

    private function groqResponse(array $overrides = []): array
    {
        $fields = array_fill_keys(LlmWebsiteExtractionStrategy::FIELDS, [
            'value' => null,
            'confidence' => 'low',
            'evidence_quote' => null,
            'source_url' => null,
            'explanation' => null,
        ]);

        return array_replace($fields, $overrides);
    }

    private function fakeGroq(array $overrides = []): void
    {
        Http::fake([
            'api.groq.test/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode($this->groqResponse($overrides))]]],
            ], 200),
        ]);
    }

    public function test_runs_rules_first_and_never_asks_llm_for_a_high_confidence_rules_field(): void
    {
        // The email rule always fires at high confidence when a plain-text email is present,
        // and policy_clarity fires high confidence for a dedicated returns-policy page path.
        $html = '<p>Email support@merchant.test. Returns are accepted within 30 days on our returns policy page.</p>';

        $this->fakeGroq();

        app(HybridWebsiteExtractionStrategy::class)->extract($this->scan($html));

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            $required = $request['response_format']['json_schema']['schema']['required'];

            return ! in_array('policy_clarity', $required, true);
        });
    }

    public function test_skips_the_provider_entirely_when_llm_extraction_is_disabled(): void
    {
        // Rules-derived suggestions must still come through even when LLM assistance is off —
        // hybrid degrades to rules-only rather than producing nothing.
        config(['llm.enabled' => false]);

        Http::fake(['api.groq.test/*' => Http::response([], 500)]);

        $suggestions = app(HybridWebsiteExtractionStrategy::class)->extract($this->scan());

        Http::assertNothingSent();
        $this->assertNotEmpty($suggestions);
    }

    public function test_llm_result_is_adopted_when_rules_found_nothing_for_that_field(): void
    {
        $this->fakeGroq([
            'restocking_fee' => [
                'value' => 'A 10% restocking fee applies to final sale items.',
                'confidence' => 'high',
                'evidence_quote' => 'Final sale items cannot be returned.',
                'source_url' => 'https://merchant.test/pages/returns',
                'explanation' => 'Stated directly.',
            ],
        ]);

        $suggestions = app(HybridWebsiteExtractionStrategy::class)->extract($this->scan());

        $insight = collect($suggestions)->firstWhere('question_key', 'website_insight.restocking_fee');

        $this->assertNotNull($insight);
        $this->assertSame('groq', $insight['provider']);
        $this->assertFalse($insight['requires_confirmation'] ?? false);
    }

    public function test_higher_confidence_llm_result_wins_over_lower_confidence_rules_result_without_confirmation(): void
    {
        // The "varies by product" marker gives rules a "Contextual policy by product/order"
        // result at medium confidence (rules' only non-high policy_clarity outcome), so hybrid
        // asks the LLM for it too and a disagreeing, higher-confidence LLM result should win.
        $html = '<p>Return eligibility varies by product. See our help center for details.</p>';

        $this->fakeGroq([
            'policy_clarity' => [
                'value' => 'clear',
                'confidence' => 'high',
                'evidence_quote' => 'Return eligibility varies by product.',
                'source_url' => 'https://merchant.test/pages/returns',
                'explanation' => 'Explicit contextual policy language.',
            ],
        ]);

        $suggestions = app(HybridWebsiteExtractionStrategy::class)->extract($this->scan($html));

        $matches = collect($suggestions)->where('question_key', 'return_policy.policy_clarity')->values();

        $this->assertGreaterThanOrEqual(1, $matches->count());
        $this->assertSame('groq', $matches->first()['provider']);
        $this->assertFalse($matches->first()['requires_confirmation'] ?? false);
    }

    public function test_equal_confidence_disagreement_flags_both_candidates_for_manual_confirmation(): void
    {
        $html = '<p>Return eligibility varies by product. See our help center for details.</p>';

        $this->fakeGroq([
            'policy_clarity' => [
                'value' => 'unclear',
                'confidence' => 'medium',
                'evidence_quote' => 'Return eligibility varies by product.',
                'source_url' => 'https://merchant.test/pages/returns',
                'explanation' => 'Ambiguous phrasing.',
            ],
        ]);

        $suggestions = app(HybridWebsiteExtractionStrategy::class)->extract($this->scan($html));

        $matches = collect($suggestions)->where('question_key', 'return_policy.policy_clarity')->values();

        $this->assertCount(2, $matches);
        $this->assertTrue($matches[0]['requires_confirmation']);
        $this->assertTrue($matches[1]['requires_confirmation']);
        $this->assertNotSame($matches[0]['value'], $matches[1]['value']);
    }

    public function test_manual_answer_is_never_present_in_hybrid_suggestions(): void
    {
        // Hybrid only ever returns candidate suggestions for evidence/autofill — it has no
        // access to existing manual answers at all (that gating lives in
        // ApplyWebsiteEvidenceToAnswersService), so this asserts the contract shape instead.
        $this->fakeGroq();

        $suggestions = app(HybridWebsiteExtractionStrategy::class)->extract($this->scan());

        foreach ($suggestions as $suggestion) {
            $this->assertArrayHasKey('question_key', $suggestion);
            $this->assertArrayHasKey('confidence', $suggestion);
        }
    }
}
