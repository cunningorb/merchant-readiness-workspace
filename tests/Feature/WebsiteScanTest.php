<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WebsiteScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_endpoint_stores_scan_evidence_and_merchant_url(): void
    {
        Http::fake([
            'example.com/contact' => Http::response('<html><body>Email support@example.com for help.</body></html>'),
            'example.com*' => Http::response('<html><head><title>Example Store</title></head><body><a href="/contact">Contact</a> Returns accepted within 45 days. Exchanges available.</body></html>'),
        ]);
        $assessment = Assessment::factory()->create();

        $response = $this->postJson("/api/assessments/{$assessment->id}/website-scan", [
            'url' => 'example.com',
        ]);

        $response->assertCreated()
            ->assertJsonPath('website_scan.status', 'completed')
            ->assertJsonPath('website_scan.url', 'https://example.com')
            ->assertJsonPath('website_scan.pages_scanned', 2)
            ->assertJsonPath('merchant.website', 'https://example.com');

        $evidence = $response->json('evidence');
        $this->assertSame('31-60 days', $evidence['return_policy.window_days'][0]['value']);
        $this->assertSame('Basic FAQ', $evidence['return_policy.policy_clarity'][0]['value']);
        $this->assertSame('website', $evidence['return_policy.window_days'][0]['source_type']);
        $this->assertTrue($evidence['exchanges.offered'][0]['value']);
        $this->assertSame('support@example.com', $evidence['business.contact_email'][0]['value']);
        $this->assertSame('Custom automation', $evidence['platform.return_tools'][0]['value']);
        $this->assertContains('return_policy.window_days', collect($response->json('answers'))->pluck('question_key'));
        $this->assertContains('return_policy.policy_clarity', collect($response->json('answers'))->pluck('question_key'));
        $this->assertContains('business.contact_email', collect($response->json('answers'))->pluck('question_key'));
        $this->assertContains('platform.return_tools', collect($response->json('answers'))->pluck('question_key'));

        $this->assertDatabaseHas('website_scans', [
            'assessment_id' => $assessment->id,
            'url' => 'https://example.com',
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
        $this->assertSame('https://example.com', $assessment->fresh()->merchant->website);
    }

    public function test_scan_prioritizes_return_policy_links_from_the_homepage(): void
    {
        Http::fake([
            'https://example.com/contact' => Http::response('<html><body>Email support@example.com for help.</body></html>'),
            'https://example.com/about' => Http::response('<html><body>About our brand.</body></html>'),
            'https://example.com/pages/returns' => Http::response('<html><body><h1>Returns</h1><p>Returns are accepted within 30 days. Exchanges are available.</p></body></html>'),
            'https://example.com*' => Http::response(<<<'HTML'
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
            'url' => 'example.com',
        ]);

        $response->assertCreated();

        $evidence = $response->json('evidence');

        $this->assertSame('15-30 days', $evidence['return_policy.window_days'][0]['value']);
        $this->assertSame('Detailed policy page', $evidence['return_policy.policy_clarity'][0]['value']);
        $this->assertTrue($evidence['exchanges.offered'][0]['value']);

        $this->assertSame(
            'https://example.com/pages/returns',
            $evidence['return_policy.window_days'][0]['evidence_url'],
        );
    }

    public function test_scan_follows_same_host_javascript_redirects(): void
    {
        Http::fake([
            'https://example.com/lander' => Http::response('<html><head><title>Redirected Store</title></head><body>Returns accepted within 30 days.</body></html>'),
            'https://example.com*' => Http::response('<!DOCTYPE html><html><head><script>window.onload=function(){window.location.href="/lander"}</script></head></html>'),
        ]);
        $assessment = Assessment::factory()->create();

        $response = $this->postJson("/api/assessments/{$assessment->id}/website-scan", [
            'url' => 'example.com',
        ]);

        $response->assertCreated()
            ->assertJsonPath('website_scan.pages_scanned', 2);

        $evidence = $response->json('evidence');

        $this->assertSame('Redirected Store', $evidence['business.company_name'][0]['value']);
        $this->assertSame('15-30 days', $evidence['return_policy.window_days'][0]['value']);
        $this->assertSame('https://example.com/lander', $evidence['return_policy.window_days'][0]['evidence_url']);
    }

    public function test_scan_uses_sitemap_when_homepage_is_javascript_shell(): void
    {
        Http::fake([
            'https://example.com/robots.txt' => Http::response("Sitemap: https://example.com/sitemap.xml\n"),
            'https://example.com/sitemap.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                    <url><loc>https://example.com/pages/returns</loc></url>
                    <url><loc>https://example.com/contact</loc></url>
                </urlset>
            XML),
            'https://example.com/pages/returns' => Http::response('<html><body><h1>Returns</h1><p>Returns accepted within 30 days. Exchanges are available.</p></body></html>'),
            'https://example.com/contact' => Http::response('<html><body>Email support@example.com for help.</body></html>'),
            'https://example.com*' => Http::response('<html><body><div id="app"></div><script src="/assets/app.js"></script></body></html>'),
        ]);
        $assessment = Assessment::factory()->create();

        $response = $this->postJson("/api/assessments/{$assessment->id}/website-scan", [
            'url' => 'example.com',
        ]);

        $response->assertCreated()
            ->assertJsonPath('website_scan.pages_scanned', 3);

        $evidence = $response->json('evidence');

        $this->assertSame('15-30 days', $evidence['return_policy.window_days'][0]['value']);
        $this->assertSame('support@example.com', $evidence['business.contact_email'][0]['value']);
        $this->assertSame('https://example.com/pages/returns', $evidence['return_policy.window_days'][0]['evidence_url']);
    }

    public function test_scan_autofill_does_not_replace_existing_answers(): void
    {
        Http::fake([
            'example.com*' => Http::response('<html><body>Returns accepted within 45 days. Exchanges available.</body></html>'),
        ]);
        $assessment = Assessment::factory()->create();
        AssessmentAnswer::factory()->create([
            'assessment_id' => $assessment->id,
            'question_key' => 'return_policy.window_days',
            'section' => 'return_policy',
            'value' => '15-30 days',
        ]);

        $this->postJson("/api/assessments/{$assessment->id}/website-scan", [
            'url' => 'example.com',
        ])->assertCreated();

        $this->assertSame(
            '15-30 days',
            $assessment->fresh()->answers()->where('question_key', 'return_policy.window_days')->value('value'),
        );
    }

    public function test_different_scan_url_replaces_previous_website_filled_answers(): void
    {
        Http::fake([
            'example.com*' => Http::response('<html><head><title>First Store</title></head><body>Email first@example.com for help.</body></html>'),
            'example.org*' => Http::response('<html><head><title>Second Store</title></head><body>Email second@example.org for help.</body></html>'),
        ]);
        $assessment = Assessment::factory()->create();

        $this->postJson("/api/assessments/{$assessment->id}/website-scan", [
            'url' => 'example.com',
        ])->assertCreated();

        $this->assertSame(
            'First Store',
            $assessment->fresh()->answers()->where('question_key', 'business.company_name')->value('value'),
        );

        $response = $this->postJson("/api/assessments/{$assessment->id}/website-scan", [
            'url' => 'example.org',
        ])->assertCreated();

        $answers = $assessment->fresh()->answers()->pluck('value', 'question_key');

        $this->assertSame('Second Store', $answers->get('business.company_name'));
        $this->assertSame('second@example.org', $answers->get('business.contact_email'));
        $this->assertNotContains(
            'https://example.com',
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

    #[DataProvider('unsafeUrlProvider')]
    public function test_scan_rejects_unsafe_urls_before_fetching(string $url): void
    {
        Http::fake();
        $assessment = Assessment::factory()->create();

        $this->postJson("/api/assessments/{$assessment->id}/website-scan", [
            'url' => $url,
        ])->assertUnprocessable()->assertJsonValidationErrors('url');

        Http::assertNothingSent();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unsafeUrlProvider(): array
    {
        return [
            'metadata service' => ['http://169.254.169.254/latest/meta-data'],
            'localhost' => ['http://localhost:8080/admin'],
            'loopback ip' => ['http://127.0.0.1:8080/admin'],
            'private ip' => ['http://192.168.1.10/admin'],
            'non http scheme' => ['file:///etc/passwd'],
        ];
    }
}
