<?php

namespace CodingSunshine\Ensemble\AI\Providers;

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
