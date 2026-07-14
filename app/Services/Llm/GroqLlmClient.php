<?php

namespace App\Services\Llm;

use App\Services\Llm\Exceptions\LlmExtractionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JsonException;

/**
 * Calls Groq's OpenAI-compatible chat-completions endpoint. Prefers strict
 * JSON schema structured output; not every model Groq hosts supports it
 * (Groq returns 400 with a response_format error for those), so this falls
 * back to plain JSON object mode and lets the caller's own field-by-field
 * validation (shape checks + evidence verification in
 * LlmWebsiteExtractionStrategy) do the work strict mode would otherwise
 * guarantee server-side. Provider-specific; consumers depend on the
 * LlmClient contract, not this class.
 */
class GroqLlmClient implements LlmClient
{
    public function extractStructured(array $messages, array $schema): array
    {
        $key = config('services.groq.key');
        $model = config('services.groq.model');

        if (! is_string($key) || $key === '' || ! is_string($model) || $model === '') {
            throw LlmExtractionException::disabled();
        }

        try {
            $response = $this->post($key, $model, $messages, $this->jsonSchemaFormat($schema));

            if ($this->rejectedJsonSchemaMode($response)) {
                $response = $this->post($key, $model, $messages, ['type' => 'json_object']);
            }
        } catch (ConnectionException) {
            throw LlmExtractionException::timedOut('groq');
        }

        if ($response->failed()) {
            throw LlmExtractionException::requestFailed('groq', $response->status());
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content)) {
            throw LlmExtractionException::invalidResponse('groq');
        }

        try {
            $decoded = json_decode($this->stripCodeFences($content), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw LlmExtractionException::invalidResponse('groq');
        }

        if (! is_array($decoded)) {
            throw LlmExtractionException::invalidResponse('groq');
        }

        return $decoded;
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $responseFormat
     */
    private function post(string $key, string $model, array $messages, array $responseFormat): Response
    {
        return Http::baseUrl(config('services.groq.base_url'))
            ->withToken($key)
            ->acceptJson()
            ->timeout((int) config('llm.timeout_seconds', 15))
            ->post('/chat/completions', [
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0,
                'response_format' => $responseFormat,
            ]);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function jsonSchemaFormat(array $schema): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'website_policy_extraction',
                'strict' => true,
                'schema' => $schema,
            ],
        ];
    }

    private function rejectedJsonSchemaMode(Response $response): bool
    {
        if ($response->status() !== 400) {
            return false;
        }

        if ($response->json('error.param') === 'response_format') {
            return true;
        }

        $message = strtolower((string) $response->json('error.message', ''));

        return str_contains($message, 'response_format') || str_contains($message, 'response format');
    }

    /**
     * json_object mode (unlike strict json_schema mode) doesn't guarantee the
     * model won't wrap its output in a markdown code fence despite being
     * told not to — strip one if present before decoding.
     */
    private function stripCodeFences(string $content): string
    {
        $trimmed = trim($content);

        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```(?:json)?\s*/i', '', $trimmed);
            $trimmed = preg_replace('/\s*```$/', '', (string) $trimmed);
        }

        return (string) $trimmed;
    }
}
