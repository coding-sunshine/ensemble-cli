<?php

namespace CodingSunshine\Ensemble\AI\Providers;

/**
 * Request/response AI provider contract for ensemble-cli (schema generation).
 *
 * This interface is intentionally different from the one in the ensemble Laravel package
 * (CodingSunshine\Ensemble\AI\Providers\ProviderContract in ensemble/src/AI/Providers/).
 * The CLI needs structured output and request/response semantics; the Studio uses
 * streaming SSE. Both packages duplicate the three core providers (OpenAI, Anthropic,
 * OpenRouter). When fixing bugs in one provider, sync the change to the other package.
 *
 * @see ensemble/src/AI/Providers/ProviderContract.php — Studio (streaming) counterpart
 */
interface ProviderContract
{
    /**
     * Send a completion request to the AI provider.
     */
    public function complete(string $system, string $user): string;

    /**
     * Send a structured completion request and return a decoded array.
     * Providers that support native JSON/tool-use modes should use them;
     * others may fall back to complete() + extractJson() + json_decode().
     *
     * @param  array<string, mixed>  $jsonSchema
     * @return array<string, mixed>
     */
    public function completeStructured(string $system, string $user, array $jsonSchema): array;

    /**
     * Perform a lightweight connectivity check. Throws RuntimeException on failure.
     */
    public function ping(): void;

    /**
     * Estimate token count for a prompt pair. Returns 0 for free/local providers.
     */
    public function estimateTokens(string $system, string $user): int;

    /**
     * Get the human-readable provider name.
     */
    public function name(): string;
}
