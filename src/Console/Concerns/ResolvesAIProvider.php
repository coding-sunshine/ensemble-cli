<?php

namespace CodingSunshine\Ensemble\Console\Concerns;

use CodingSunshine\Ensemble\AI\Providers\AnthropicProvider;
use CodingSunshine\Ensemble\AI\Providers\OllamaProvider;
use CodingSunshine\Ensemble\AI\Providers\OpenAIProvider;
use CodingSunshine\Ensemble\AI\Providers\OpenRouterProvider;
use CodingSunshine\Ensemble\AI\Providers\PrismProvider;
use CodingSunshine\Ensemble\AI\Providers\ProviderContract;
use CodingSunshine\Ensemble\Config\ConfigStore;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

trait ResolvesAIProvider
{
    protected ?ConfigStore $configStore = null;

    /**
     * Resolve the AI provider from CLI options, saved config, environment, or interactive prompts.
     * Runs a health check before returning.
     */
    protected function resolveProvider(InputInterface $input): ProviderContract
    {
        $this->configStore ??= new ConfigStore();

        $providerName = $this->resolveProviderName($input);
        $model = $input->getOption('model');

        $provider = match ($providerName) {
            'anthropic' => new AnthropicProvider(
                apiKey: $this->resolveApiKey($input, 'ANTHROPIC_API_KEY', 'Anthropic', 'anthropic'),
                model: $model ?: 'claude-sonnet-4-20250514',
            ),
            'openai' => new OpenAIProvider(
                apiKey: $this->resolveApiKey($input, 'OPENAI_API_KEY', 'OpenAI', 'openai'),
                model: $model ?: 'gpt-4o',
            ),
            'openrouter' => new OpenRouterProvider(
                apiKey: $this->resolveApiKey($input, 'OPENROUTER_API_KEY', 'OpenRouter', 'openrouter'),
                model: $model ?: 'anthropic/claude-sonnet-4',
            ),
            'ollama' => new OllamaProvider(
                model: $model ?: 'llama3.1',
            ),
            'prism' => new PrismProvider(
                provider: ($input->hasOption('prism-provider') ? $input->getOption('prism-provider') : null),
                model: $model ?: null,
            ),
            default => throw new \InvalidArgumentException(
                "Unknown provider [{$providerName}]. Supported: anthropic, openai, openrouter, ollama, prism."
            ),
        };

        $this->verifyProviderConnection($provider);

        return $provider;
    }

    /**
     * Determine which provider to use from flags, saved config, or interactive prompt.
     */
    protected function resolveProviderName(InputInterface $input): string
    {
        if ($providerName = $input->getOption('provider')) {
            return $providerName;
        }

        $savedProvider = $this->configStore->get('default_provider');

        if ($savedProvider) {
            $useSaved = confirm(
                label: "Use saved provider <options=bold>{$savedProvider}</>?",
                default: true,
            );

            if ($useSaved) {
                return $savedProvider;
            }
        }

        $providerName = select(
            label: 'Which AI provider would you like to use?',
            options: [
                'anthropic' => 'Anthropic (Claude)',
                'openai' => 'OpenAI (GPT-4o)',
                'openrouter' => 'OpenRouter (multi-model)',
                'ollama' => 'Ollama (local, free)',
                'prism' => 'Prism (prism-php/prism)',
            ],
            default: $savedProvider ?: 'anthropic',
        );

        if ($providerName !== $savedProvider) {
            $this->configStore->set('default_provider', $providerName);
        }

        return $providerName;
    }

    /**
     * Resolve an API key from CLI option, environment, saved config, or interactive prompt.
     * Offers to save interactively-entered keys for future use.
     */
    protected function resolveApiKey(
        InputInterface $input,
        string $envVariable,
        string $providerLabel,
        string $providerKey,
    ): string {
        if ($apiKey = $input->getOption('api-key')) {
            return $apiKey;
        }

        if ($apiKey = $_ENV['ENSEMBLE_API_KEY'] ?? getenv('ENSEMBLE_API_KEY') ?: null) {
            return $apiKey;
        }

        if ($apiKey = $_ENV[$envVariable] ?? getenv($envVariable) ?: null) {
            return $apiKey;
        }

        $savedKey = $this->configStore->get("providers.{$providerKey}.api_key");

        if ($savedKey) {
            $maskedKey = substr($savedKey, 0, 8).'...'.substr($savedKey, -4);

            $useSaved = confirm(
                label: "Use saved {$providerLabel} API key ({$maskedKey})?",
                default: true,
            );

            if ($useSaved) {
                return $savedKey;
            }
        }

        $apiKey = password(
            label: "Enter your {$providerLabel} API key",
            required: true,
            hint: "Set {$envVariable} or ENSEMBLE_API_KEY env variable to skip this prompt.",
        );

        if ($input->isInteractive()) {
            $shouldSave = confirm(
                label: 'Save this API key for future use?',
                default: true,
                hint: 'Stored in ~/.ensemble/config.json (file permissions: 600)',
            );

            if ($shouldSave) {
                $this->configStore->set("providers.{$providerKey}.api_key", $apiKey);
                info('API key saved to ~/.ensemble/config.json');
            }
        }

        return $apiKey;
    }

    /**
     * Verify the provider can connect before starting the interview.
     */
    protected function verifyProviderConnection(ProviderContract $provider): void
    {
        try {
            spin(
                message: "Verifying {$provider->name()} connection...",
                callback: fn () => $provider->ping(),
            );

            info("{$provider->name()} connected successfully.");
        } catch (RuntimeException $exception) {
            warning("Connection failed: {$exception->getMessage()}");
            throw $exception;
        }
    }
}
