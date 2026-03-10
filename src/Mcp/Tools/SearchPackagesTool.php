<?php

namespace CodingSunshine\Ensemble\Mcp\Tools;

use CodingSunshine\Ensemble\Http\LaraPluginsClient;

class SearchPackagesTool implements McpToolInterface
{
    public function name(): string
    {
        return 'search_packages';
    }

    public function description(): string
    {
        return <<<'DESC'
Search Laravel packages on laraplugins.io by keyword.

Returns a list of matching packages with their health score, description, and Composer package name.
Each result contains:
  - package_name: Composer vendor/package identifier (e.g. "spatie/laravel-permission")
  - description: short package description
  - health_score: "healthy" | "medium" | "unhealthy" (or null if unknown)

Set healthy_only=true to filter out unhealthy packages and return only healthy or medium-rated ones.

Example use: search for "media upload" to find packages for file/image handling.
DESC;
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Search keyword, e.g. "media upload", "billing", "permissions"',
                ],
                'healthy_only' => [
                    'type' => 'boolean',
                    'description' => 'When true, exclude packages rated unhealthy. Defaults to false.',
                ],
            ],
            'required' => ['query'],
        ];
    }

    public function execute(array $arguments): mixed
    {
        $query = $arguments['query'] ?? '';
        $healthyOnly = (bool) ($arguments['healthy_only'] ?? false);

        $client = new LaraPluginsClient();
        $results = $client->search($query);

        if ($healthyOnly) {
            $results = array_values(array_filter($results, function (array $result) {
                $health = strtolower((string) ($result['health_score'] ?? $result['health'] ?? 'healthy'));

                return ! in_array($health, ['unhealthy', 'poor', 'low', 'bad'], true);
            }));
        }

        return $results;
    }
}
