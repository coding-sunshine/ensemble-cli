<?php

namespace CodingSunshine\Ensemble\AI\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use RuntimeException;

class AnthropicProvider implements ProviderContract
{
    protected readonly Client $client;

    public function __construct(
        protected readonly string $apiKey,
        protected readonly string $model = 'claude-sonnet-4-20250514',
    ) {
        $this->client = new Client([
            'base_uri' => 'https://api.anthropic.com/',
            'timeout' => 120,
        ]);
    }

    public function complete(string $system, string $user): string
    {
        try {
            $response = $this->client->post('v1/messages', [
                'headers' => [
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'max_tokens' => 4096,
                    'system' => $system,
                    'messages' => [
                        ['role' => 'user', 'content' => $user],
                    ],
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            return $body['content'][0]['text'] ?? '';
        } catch (ClientException $exception) {
            $status = $exception->getResponse()->getStatusCode();

            if ($status === 401) {
                throw new RuntimeException(
                    'Invalid Anthropic API key. Check your key or set ANTHROPIC_API_KEY / ENSEMBLE_API_KEY in your environment.'
                );
            }

            if ($status === 429) {
                throw new RuntimeException(
                    'Anthropic rate limit exceeded. Please wait a moment and try again.'
                );
            }

            throw new RuntimeException(
                "Anthropic API error ({$status}): ".$exception->getResponse()->getBody()->getContents()
            );
        } catch (ConnectException $exception) {
            throw new RuntimeException(
                'Could not connect to Anthropic API. Check your internet connection.'
            );
        }
    }

    public function ping(): void
    {
        try {
            $this->client->post('v1/messages', [
                'headers' => [
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'max_tokens' => 1,
                    'messages' => [
                        ['role' => 'user', 'content' => 'hi'],
                    ],
                ],
            ]);
        } catch (ClientException $exception) {
            $status = $exception->getResponse()->getStatusCode();

            if ($status === 401) {
                throw new RuntimeException(
                    'Invalid Anthropic API key. Check your key or set ANTHROPIC_API_KEY / ENSEMBLE_API_KEY in your environment.'
                );
            }

            throw new RuntimeException("Anthropic API error ({$status}) during connectivity check.");
        } catch (ConnectException) {
            throw new RuntimeException('Could not connect to Anthropic API. Check your internet connection.');
        }
    }

    public function completeStructured(string $system, string $user, array $jsonSchema): array
    {
        $toolName = $jsonSchema['name'] ?? 'structured_output';
        $toolDescription = $jsonSchema['description'] ?? 'Return structured data';

        // Build the input_schema from the jsonSchema, removing our custom keys
        $inputSchema = $jsonSchema;
        unset($inputSchema['name'], $inputSchema['description']);

        try {
            $response = $this->client->post('v1/messages', [
                'headers' => [
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'max_tokens' => 4096,
                    'system' => $system,
                    'messages' => [
                        ['role' => 'user', 'content' => $user],
                    ],
                    'tools' => [
                        [
                            'name' => $toolName,
                            'description' => $toolDescription,
                            'input_schema' => $inputSchema,
                        ],
                    ],
                    'tool_choice' => ['type' => 'tool', 'name' => $toolName],
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            // Extract from tool_use content block
            foreach ($body['content'] ?? [] as $block) {
                if (($block['type'] ?? '') === 'tool_use' && isset($block['input'])) {
                    return $block['input'];
                }
            }

            return [];
        } catch (ClientException $exception) {
            $status = $exception->getResponse()->getStatusCode();

            if ($status === 401) {
                throw new RuntimeException(
                    'Invalid Anthropic API key. Check your key or set ANTHROPIC_API_KEY / ENSEMBLE_API_KEY in your environment.'
                );
            }

            if ($status === 429) {
                throw new RuntimeException(
                    'Anthropic rate limit exceeded. Please wait a moment and try again.'
                );
            }

            throw new RuntimeException(
                "Anthropic API error ({$status}): ".$exception->getResponse()->getBody()->getContents()
            );
        } catch (ConnectException $exception) {
            throw new RuntimeException(
                'Could not connect to Anthropic API. Check your internet connection.'
            );
        }
    }

    public function estimateTokens(string $system, string $user): int
    {
        return (int) ceil((strlen($system) + strlen($user)) / 4);
    }

    public function name(): string
    {
        return 'Anthropic';
    }
}
