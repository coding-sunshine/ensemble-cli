<?php

namespace CodingSunshine\Ensemble\AI\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use RuntimeException;

class OpenAIProvider implements ProviderContract
{
    protected readonly Client $client;

    public function __construct(
        protected readonly string $apiKey,
        protected readonly string $model = 'gpt-4o',
    ) {
        $this->client = new Client([
            'base_uri' => 'https://api.openai.com/',
            'timeout' => 120,
        ]);
    }

    public function complete(string $system, string $user): string
    {
        try {
            $response = $this->client->post('v1/chat/completions', [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
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
                    'Invalid OpenAI API key. Check your key or set OPENAI_API_KEY / ENSEMBLE_API_KEY in your environment.'
                );
            }

            if ($status === 429) {
                throw new RuntimeException(
                    'OpenAI rate limit exceeded. Please wait a moment and try again.'
                );
            }

            throw new RuntimeException(
                "OpenAI API error ({$status}): ".$exception->getResponse()->getBody()->getContents()
            );
        } catch (ConnectException $exception) {
            throw new RuntimeException(
                'Could not connect to OpenAI API. Check your internet connection.'
            );
        }
    }

    public function ping(): void
    {
        try {
            $this->client->get('v1/models', [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                ],
            ]);
        } catch (ClientException $exception) {
            $status = $exception->getResponse()->getStatusCode();

            if ($status === 401) {
                throw new RuntimeException(
                    'Invalid OpenAI API key. Check your key or set OPENAI_API_KEY / ENSEMBLE_API_KEY in your environment.'
                );
            }

            throw new RuntimeException("OpenAI API error ({$status}) during connectivity check.");
        } catch (ConnectException) {
            throw new RuntimeException('Could not connect to OpenAI API. Check your internet connection.');
        }
    }

    public function completeStructured(string $system, string $user, array $jsonSchema): array
    {
        $schemaName = $jsonSchema['name'] ?? 'structured_output';

        // Build schema object without our custom keys
        $schema = $jsonSchema;
        unset($schema['name'], $schema['description']);

        try {
            $response = $this->client->post('v1/chat/completions', [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'max_tokens' => 4096,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => [
                            'name' => $schemaName,
                            'schema' => $schema,
                            'strict' => false,
                        ],
                    ],
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);
            $content = $body['choices'][0]['message']['content'] ?? '{}';

            return json_decode($content, true) ?? [];
        } catch (ClientException $exception) {
            $status = $exception->getResponse()->getStatusCode();

            if ($status === 401) {
                throw new RuntimeException(
                    'Invalid OpenAI API key. Check your key or set OPENAI_API_KEY / ENSEMBLE_API_KEY in your environment.'
                );
            }

            if ($status === 429) {
                throw new RuntimeException(
                    'OpenAI rate limit exceeded. Please wait a moment and try again.'
                );
            }

            throw new RuntimeException(
                "OpenAI API error ({$status}): ".$exception->getResponse()->getBody()->getContents()
            );
        } catch (ConnectException $exception) {
            throw new RuntimeException(
                'Could not connect to OpenAI API. Check your internet connection.'
            );
        }
    }

    public function estimateTokens(string $system, string $user): int
    {
        return (int) ceil((strlen($system) + strlen($user)) / 4);
    }

    public function name(): string
    {
        return 'OpenAI';
    }
}
