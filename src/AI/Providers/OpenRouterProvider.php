<?php

namespace CodingSunshine\Ensemble\AI\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use RuntimeException;

class OpenRouterProvider implements ProviderContract
{
    protected readonly Client $client;

    public function __construct(
        protected readonly string $apiKey,
        protected readonly string $model = 'anthropic/claude-sonnet-4',
    ) {
        $this->client = new Client([
            'base_uri' => 'https://openrouter.ai/',
            'timeout' => 120,
        ]);
    }

    public function complete(string $system, string $user): string
    {
        try {
            $response = $this->client->post('api/v1/chat/completions', [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => 'https://github.com/coding-sunshine/ensemble-cli',
                    'X-Title' => 'Laravel Ensemble CLI',
                ],
                'json' => [
                    'model' => $this->model,
                    'max_tokens' => 4096,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            return $body['choices'][0]['message']['content'] ?? '';
        } catch (ClientException $exception) {
            $status = $exception->getResponse()->getStatusCode();

            if ($status === 401) {
                throw new RuntimeException(
                    'Invalid OpenRouter API key. Check your key or set OPENROUTER_API_KEY / ENSEMBLE_API_KEY in your environment.'
                );
            }

            if ($status === 429) {
                throw new RuntimeException(
                    'OpenRouter rate limit exceeded. Please wait a moment and try again.'
                );
            }

            throw new RuntimeException(
                "OpenRouter API error ({$status}): ".$exception->getResponse()->getBody()->getContents()
            );
        } catch (ConnectException $exception) {
            throw new RuntimeException(
                'Could not connect to OpenRouter API. Check your internet connection.'
            );
        }
    }

    public function ping(): void
    {
        try {
            $this->client->get('api/v1/models', [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                ],
            ]);
        } catch (ClientException $exception) {
            $status = $exception->getResponse()->getStatusCode();

            if ($status === 401) {
                throw new RuntimeException(
                    'Invalid OpenRouter API key. Check your key or set OPENROUTER_API_KEY / ENSEMBLE_API_KEY in your environment.'
                );
            }

            throw new RuntimeException("OpenRouter API error ({$status}) during connectivity check.");
        } catch (ConnectException) {
            throw new RuntimeException('Could not connect to OpenRouter API. Check your internet connection.');
        }
    }

    public function completeStructured(string $system, string $user, array $jsonSchema): array
    {
        $rawResponse = $this->complete($system, $user);
        $json = $this->extractJson($rawResponse);

        return json_decode($json, true) ?? [];
    }

    public function estimateTokens(string $system, string $user): int
    {
        return (int) ceil((strlen($system) + strlen($user)) / 4);
    }

    public function name(): string
    {
        return 'OpenRouter';
    }

    protected function extractJson(string $response): string
    {
        $response = trim($response);

        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $response, $matches)) {
            return trim($matches[1]);
        }

        return $response;
    }
}
