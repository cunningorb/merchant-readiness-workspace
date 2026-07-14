<?php

namespace App\Services\Llm;

/**
 * Provider-neutral seam for structured-extraction LLM calls. Implementations
 * (GroqLlmClient today; OpenRouter/Gemini clients later) must never leak
 * provider credentials, prompts, or raw page content into thrown exceptions —
 * callers may log exception messages.
 */
interface LlmClient
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     *
     * @throws Exceptions\LlmExtractionException
     */
    public function extractStructured(array $messages, array $schema): array;
}
