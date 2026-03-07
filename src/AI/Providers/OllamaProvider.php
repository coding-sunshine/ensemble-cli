<?php

namespace CodingSunshine\Ensemble\AI\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use RuntimeException;

class OllamaProvider implements ProviderContract
{
    protected readonly Client $client;

    protected readonly string $baseUrl;

    public function __construct(
        protected readonly string $model = 'llama3.1',
        ?string $baseUrl = null,
    ) {
        $this->baseUrl = $baseUrl
            ?? $_ENV['OLLAMA_HOST'] ?? getenv('OLLAMA_HOST') ?: 'http://localhost:11434';

        $normalizedUrl = rtrim($this->baseUrl, '/').'/';

        $this->client = new Client([
            'base_uri' => $normalizedUrl,
            'timeout' => 300,
        ]);
    }

    public function complete(string $system, string $user): string
    {
        try {
            $response = $this->client->post('api/chat', [
                'json' => [
                    'model' => $this->model,
                    'stream' => false,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            return $body['message']['content'] ?? '';
        } catch (ConnectException) {
            throw new RuntimeException(
                "Could not connect to Ollama at {$this->baseUrl}. Is Ollama running? Start it with: ollama serve"
                .($this->baseUrl !== 'http://localhost:11434' ? '' : "\n  Set OLLAMA_HOST to use a different address.")
            );
        }
    }

    public function ping(): void
    {
        try {
            $this->client->get('api/tags');
        } catch (ConnectException) {
            throw new RuntimeException(
                "Could not connect to Ollama at {$this->baseUrl}. Is Ollama running? Start it with: ollama serve"
            );
        }
    }

    public function estimateTokens(string $system, string $user): int
    {
        return 0;
    }

    public function name(): string
    {
        return 'Ollama';
    }
}
