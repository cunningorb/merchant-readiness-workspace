<?php

namespace App\Services\Llm\Exceptions;

use RuntimeException;

/**
 * Deliberately carries only safe, static messages — provider name and status
 * code at most. Never construct this with prompt text, page content, or
 * response bodies, since callers log the message as-is.
 */
class LlmExtractionException extends RuntimeException
{
    public static function disabled(): self
    {
        return new self('LLM extraction is disabled.');
    }

    public static function requestFailed(string $provider, int $status): self
    {
        return new self("LLM extraction request to {$provider} failed with status {$status}.");
    }

    public static function timedOut(string $provider): self
    {
        return new self("LLM extraction request to {$provider} timed out.");
    }

    public static function invalidResponse(string $provider): self
    {
        return new self("LLM extraction response from {$provider} was not valid structured JSON.");
    }
}
