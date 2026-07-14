<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebsiteScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_endpoint_stores_scan_evidence_and_merchant_url(): void
    {
        Http::fake([
            'example.test/contact' => Http::response('<html><body>Email support@example.test for help.</body></html>'),
            'example.test*' => Http::response('<html><head><title>Example Store</title></head><body><a href="/contact">Contact</a> Returns accepted within 45 days. Exchanges available.</body></html>'),
        ]);
        $assessment = Assessment::factory()->create();

        $response = $this->postJson("/api/assessments/{$assessment->id}/website-scan", [
            'url' => 'example.test',
        ]);

        $response->assertCreated()
            ->assertJsonPath('website_scan.status', 'completed')
            ->assertJsonPath('website_scan.url', 'https://example.test')
            ->assertJsonPath('website_scan.pages_scanned', 2)
            ->assertJsonPath('merchant.website', 'https://example.test');

        $evidence = $response->json('evidence');
        $this->assertSame('31-60 days', $evidence['return_policy.window_days'][0]['value']);
        $this->assertSame('Basic FAQ', $evidence['return_policy.policy_clarity'][0]['value']);
        $this->assertSame('website', $evidence['return_policy.window_days'][0]['source_type']);
        $this->assertTrue($evidence['exchanges.offered'][0]['value']);
        $this->assertSame('support@example.test', $evidence['business.contact_email'][0]['value']);
        $this->assertSame('Custom automation', $evidence['platform.return_tools'][0]['value']);
        $this->assertContains('return_policy.window_days', collect($response->json('answers'))->pluck('question_key'));
        $this->assertContains('return_policy.policy_clarity', collect($response->json('answers'))->pluck('question_key'));
        $this->assertContains('business.contact_email', collect($response->json('answers'))->pluck('question_key'));
        $this->assertContains('platform.return_tools', collect($response->json('answers'))->pluck('question_key'));

        $this->assertDatabaseHas('website_scans', [
            'assessment_id' => $assessment->id,
            'url' => 'https://example.test',
            'pages_scanned' => 2,
        ]);
        $this->assertDatabaseHas('assessment_answer_evidence', [
            'assessment_id' => $assessment->id,
            'question_key' => 'return_policy.window_days',
            'source_type' => 'website',
            'confidence' => 'high',
        ]);
        $this->assertDatabaseHas('assessment_answers', [
            'assessment_id' => $assessment->id,
            'question_key' => 'return_policy.window_days',
        ]);
        $this->assertDatabaseHas('assessment_answers', [
            'assessment_id' => $assessment->id,
            'question_key' => 'return_policy.policy_clarity',
        ]);
        $this->assertDatabaseHas('assessment_answers', [
            'assessment_id' => $assessment->id,
            'question_key' => 'business.contact_email',
        ]);
        $this->assertDatabaseHas('assessment_answers', [
            'assessment_id' => $assessment->id,
            'question_key' => 'platform.return_tools',
        ]);
        $this->assertSame('https://example.test', $assessment->fresh()->merchant->website);
    }

    public function test_scan_prioritizes_return_policy_links_from_the_homepage(): void
    {
        Http::fake([
            'https://example.test/contact' => Http::response('<html><body>Email support@example.test for help.</body></html>'),
            'https://example.test/about' => Http::response('<html><body>About our brand.</body></html>'),
            'https://example.test/pages/returns' => Http::response('<html><body><h1>Returns</h1><p>Returns are accepted within 30 days. Exchanges are available.</p></body></html>'),
            'https://example.test*' => Http::response(<<<'HTML'
                <html>
                    <head><title>Example Store</title></head>
                    <body>
                        <a href="/contact">Contact</a>
                        <a href="/about">About</a>
                        <a href="/pages/returns">Returns</a>
                    </body>
                </html>
            HTML),
        ]);
        $assessment = Assessment::factory()->create();

        $response = $this->postJson("/api/assessments/{$assessment->id}/website-scan", [
            'url' => 'example.test',
        ]);

        $response->assertCreated();

        $evidence = $response->json('evidence');

        $this->assertSame('15-30 days', $evidence['return_policy.window_days'][0]['value']);
        $this->assertSame('Detailed policy page', $evidence['return_policy.policy_clarity'][0]['value']);
        $this->assertTrue($evidence['exchanges.offered'][0]['value']);

        $this->assertSame(
            'https://example.test/pages/returns',
            $evidence['return_policy.window_days'][0]['evidence_url'],
        );
    }

    public function test_scan_autofill_does_not_replace_existing_answers(): void
    {
        Http::fake([
            'example.test*' => Http::response('<html><body>Returns accepted within 45 days. Exchanges available.</body></html>'),
        ]);
        $assessment = Assessment::factory()->create();
        AssessmentAnswer::factory()->create([
            'assessment_id' => $assessment->id,
            'question_key' => 'return_policy.window_days',
            'section' => 'return_policy',
            'value' => '15-30 days',
        ]);

        $this->postJson("/api/assessments/{$assessment->id}/website-scan", [
            'url' => 'example.test',
        ])->assertCreated();

        $this->assertSame(
            '15-30 days',
            $assessment->fresh()->answers()->where('question_key', 'return_policy.window_days')->value('value'),
        );
    }

    public function test_different_scan_url_replaces_previous_website_filled_answers(): void
    {
        Http::fake([
            'first.test*' => Http::response('<html><head><title>First Store</title></head><body>Email first@example.test for help.</body></html>'),
            'second.test*' => Http::response('<html><head><title>Second Store</title></head><body>Email second@example.test for help.</body></html>'),
        ]);
        $assessment = Assessment::factory()->create();

        $this->postJson("/api/assessments/{$assessment->id}/website-scan", [
            'url' => 'first.test',
        ])->assertCreated();

        $this->assertSame(
            'First Store',
            $assessment->fresh()->answers()->where('question_key', 'business.company_name')->value('value'),
        );

        $response = $this->postJson("/api/assessments/{$assessment->id}/website-scan", [
            'url' => 'second.test',
        ])->assertCreated();

        $answers = $assessment->fresh()->answers()->pluck('value', 'question_key');

        $this->assertSame('Second Store', $answers->get('business.company_name'));
        $this->assertSame('second@example.test', $answers->get('business.contact_email'));
        $this->assertNotContains(
            'https://first.test',
            collect($response->json('evidence'))->flatMap(fn (array $records): array => $records)->pluck('evidence_url')->all(),
        );
    }

    public function test_scan_requires_a_url(): void
    {
        $assessment = Assessment::factory()->create();

        $this->postJson("/api/assessments/{$assessment->id}/website-scan", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('url');
    }
}
