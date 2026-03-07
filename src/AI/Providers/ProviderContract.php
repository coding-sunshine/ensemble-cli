<?php

namespace CodingSunshine\Ensemble\AI\Providers;

interface ProviderContract
{
    /**
     * Send a completion request to the AI provider.
     */
    public function complete(string $system, string $user): string;

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
