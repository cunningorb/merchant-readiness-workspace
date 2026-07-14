<?php

namespace Tests\Unit\Services\Llm;

use App\Services\Llm\Exceptions\LlmExtractionException;
use App\Services\Llm\GroqLlmClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GroqLlmClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.groq.key' => 'gsk_test_key',
            'services.groq.base_url' => 'https://api.groq.test/openai/v1',
            'services.groq.model' => 'test-model',
            'llm.timeout_seconds' => 15,
        ]);
    }

    public function test_posts_messages_and_schema_to_the_chat_completions_endpoint(): void
    {
        Http::fake([
            'api.groq.test/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode(['return_window_days' => 30])]],
                ],
            ], 200),
        ]);

        $client = new GroqLlmClient;

        $result = $client->extractStructured(
            messages: [['role' => 'user', 'content' => 'extract things']],
            schema: ['type' => 'object'],
        );

        $this->assertSame(['return_window_days' => 30], $result);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.groq.test/openai/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer gsk_test_key')
                && $request['model'] === 'test-model'
                && $request['response_format']['type'] === 'json_schema';
        });
    }

    public function test_throws_disabled_when_api_key_missing(): void
    {
        config(['services.groq.key' => null]);

        $this->expectException(LlmExtractionException::class);
        $this->expectExceptionMessage('LLM extraction is disabled.');

        (new GroqLlmClient)->extractStructured([], ['type' => 'object']);
    }

    public function test_throws_disabled_when_model_missing(): void
    {
        config(['services.groq.model' => null]);

        $this->expectException(LlmExtractionException::class);

        (new GroqLlmClient)->extractStructured([], ['type' => 'object']);
    }

    public function test_falls_back_to_json_object_mode_when_the_model_rejects_json_schema(): void
    {
        Http::fake([
            'api.groq.test/*' => Http::sequence()
                ->push(['error' => [
                    'message' => 'This model does not support response format `json_schema`. See supported models at ...',
                    'type' => 'invalid_request_error',
                    'param' => 'response_format',
                ]], 400)
                ->push([
                    'choices' => [['message' => ['content' => json_encode(['return_window_days' => 30])]]],
                ], 200),
        ]);

        $result = (new GroqLlmClient)->extractStructured(
            messages: [['role' => 'user', 'content' => 'extract things']],
            schema: ['type' => 'object'],
        );

        $this->assertSame(['return_window_days' => 30], $result);

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => $request['response_format']['type'] === 'json_object');
    }

    public function test_does_not_retry_a_400_that_is_not_about_response_format(): void
    {
        Http::fake(['api.groq.test/*' => Http::response(['error' => ['message' => 'invalid api key']], 400)]);

        $this->expectException(LlmExtractionException::class);
        $this->expectExceptionMessage('failed with status 400');

        (new GroqLlmClient)->extractStructured([], ['type' => 'object']);

        Http::assertSentCount(1);
    }

    public function test_throws_request_failed_on_non_2xx_response(): void
    {
        Http::fake(['api.groq.test/*' => Http::response(['error' => 'nope'], 429)]);

        $this->expectException(LlmExtractionException::class);
        $this->expectExceptionMessage('failed with status 429');

        (new GroqLlmClient)->extractStructured([], ['type' => 'object']);
    }

    public function test_throws_invalid_response_when_content_is_not_a_string(): void
    {
        Http::fake([
            'api.groq.test/*' => Http::response(['choices' => [['message' => ['content' => null]]]], 200),
        ]);

        $this->expectException(LlmExtractionException::class);
        $this->expectExceptionMessage('not valid structured JSON');

        (new GroqLlmClient)->extractStructured([], ['type' => 'object']);
    }

    public function test_throws_invalid_response_when_content_is_not_valid_json(): void
    {
        Http::fake([
            'api.groq.test/*' => Http::response(['choices' => [['message' => ['content' => '{not json']]]], 200),
        ]);

        $this->expectException(LlmExtractionException::class);
        $this->expectExceptionMessage('not valid structured JSON');

        (new GroqLlmClient)->extractStructured([], ['type' => 'object']);
    }

    public function test_throws_timed_out_on_connection_exception(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
        });

        $this->expectException(LlmExtractionException::class);
        $this->expectExceptionMessage('timed out');

        (new GroqLlmClient)->extractStructured([], ['type' => 'object']);
    }

    public function test_exception_messages_never_contain_the_api_key(): void
    {
        Http::fake(['api.groq.test/*' => Http::response(['error' => 'nope'], 500)]);

        try {
            (new GroqLlmClient)->extractStructured([], ['type' => 'object']);
            $this->fail('Expected LlmExtractionException.');
        } catch (LlmExtractionException $exception) {
            $this->assertStringNotContainsString('gsk_test_key', $exception->getMessage());
        }
    }
}
