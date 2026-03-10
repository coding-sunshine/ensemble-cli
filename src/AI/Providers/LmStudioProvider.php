<?php

namespace CodingSunshine\Ensemble\AI\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use RuntimeException;

/**
 * AI provider for LM Studio (OpenAI-compatible server at http://localhost:1234/v1).
 * No API key required — local only.
 */
class LmStudioProvider implements ProviderContract
{
    protected readonly Client $client;

    protected readonly string $baseUrl;

    public function __construct(
        protected readonly string $model = 'local',
        ?string $baseUrl = null,
    ) {
        $this->baseUrl = rtrim($baseUrl ?? 'http://localhost:1234', '/') . '/v1';

        $this->client = new Client([
            'base_uri' => $this->baseUrl . '/',
            'timeout'  => 300,
        ]);
    }

    public function complete(string $system, string $user): string
    {
        try {
            $response = $this->client->post('chat/completions', [
                'json' => [
                    'model'      => $this->model,
                    'max_tokens' => 4096,
                    'messages'   => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            return $body['choices'][0]['message']['content'] ?? '';
        } catch (ConnectException $e) {
            throw new RuntimeException(
                "Could not connect to LM Studio at {$this->baseUrl}. Is LM Studio running with a model loaded?"
            );
        }
    }

    public function ping(): void
    {
        try {
            $this->client->get('models');
        } catch (ConnectException $e) {
            throw new RuntimeException(
                "Could not connect to LM Studio at {$this->baseUrl}. Is LM Studio running?"
            );
        }
    }

    public function completeStructured(string $system, string $user, array $jsonSchema): array
    {
        $raw = $this->complete($system, $user . "\n\nRespond with a single valid JSON object only, no markdown.");
        $text = trim($raw);

        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $text, $matches)) {
            $text = trim($matches[1]);
        }

        if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
            $decoded = json_decode($matches[0], true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    public function estimateTokens(string $system, string $user): int
    {
        return 0;
    }

    public function name(): string
    {
        return 'LM Studio';
    }
}
