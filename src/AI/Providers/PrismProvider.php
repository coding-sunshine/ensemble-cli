<?php

namespace CodingSunshine\Ensemble\AI\Providers;

use RuntimeException;

/**
 * Structured-output AI provider backed by prism-php/prism (CLI package).
 *
 * Requires the prism-php/prism package AND a Laravel application context:
 *   composer require prism-php/prism
 *
 * Prism is a Laravel package; the standalone `ensemble` CLI binary cannot use it
 * without a bootstrapped Laravel container. To use Prism, run ensemble-cli commands
 * from inside a Laravel application where Prism is registered.
 *
 * Set --provider=prism (or ENSEMBLE_AI_PROVIDER=prism) when calling ensemble commands.
 * Configure Prism normally via config/prism.php (provider, api key, model).
 */
class PrismProvider implements ProviderContract
{
    public function __construct(
        private readonly ?string $provider = null,
        private readonly ?string $model = null,
    ) {}

    public function complete(string $system, string $user): string
    {
        $this->assertPrismAvailable();

        $prism = app('EchoLabs\Prism\Prism');

        /** @phpstan-ignore-next-line */
        $generator = $this->applyProviderAndModel($prism->text())
            ->withSystemPrompt($system)
            ->withPrompt($user);

        try {
            /** @phpstan-ignore-next-line */
            return $generator->generate()->text;
        } catch (\Throwable $e) {
            throw new RuntimeException('Prism AI error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function completeStructured(string $system, string $user, array $jsonSchema): array
    {
        $text    = $this->complete($system, $user);
        $json    = $this->extractJson($text);
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Prism returned invalid JSON for structured output.');
        }

        return $decoded;
    }

    public function ping(): void
    {
        $this->assertPrismAvailable();

        try {
            $this->complete('You are a ping test.', 'Reply with only the word "pong".');
        } catch (\Throwable $e) {
            throw new RuntimeException('Prism connectivity check failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function estimateTokens(string $system, string $user): int
    {
        return (int) ceil((strlen($system) + strlen($user)) / 4);
    }

    public function name(): string
    {
        $suffix = $this->provider !== null ? " ({$this->provider})" : '';

        return 'Prism' . $suffix;
    }

    /**
     * Apply configured provider and model to a Prism generator.
     *
     * @param  mixed  $generator  Prism text generator instance
     * @return mixed
     */
    private function applyProviderAndModel(mixed $generator): mixed
    {
        if ($this->provider !== null) {
            /** @phpstan-ignore-next-line */
            return $generator->using($this->provider, $this->model ?? '');
        }

        return $generator;
    }

    /**
     * Strip markdown code fences and return raw JSON string.
     */
    private function extractJson(string $text): string
    {
        $text = trim($text);

        if (str_starts_with($text, '```')) {
            $text = (string) preg_replace('/^```(?:json)?\s*/i', '', $text);
            $text = (string) preg_replace('/\s*```$/', '', $text);
            $text = trim($text);
        }

        return $text;
    }

    /**
     * Assert that Prism is installed and a Laravel container is available.
     *
     * Prism is a Laravel package. In standalone CLI mode (the `ensemble` binary),
     * the Laravel container is not available. To use Prism from the CLI you must
     * run inside a Laravel application that bootstraps the container.
     */
    private function assertPrismAvailable(): void
    {
        if (! class_exists('EchoLabs\Prism\Prism')) {
            throw new RuntimeException(
                'prism-php/prism is not installed. Run: composer require prism-php/prism'
            );
        }

        if (! function_exists('app') || ! app()->bound('EchoLabs\Prism\Prism')) {
            throw new RuntimeException(
                'Prism requires a Laravel application container. ' .
                'Use the "prism" provider only inside a Laravel project, ' .
                'or choose another provider (anthropic, openai, openrouter, ollama) for standalone CLI use.'
            );
        }
    }
}
