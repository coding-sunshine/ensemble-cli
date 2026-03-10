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
        return 'Search Laravel packages on laraplugins.io.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Search query',
                ],
            ],
            'required' => ['query'],
        ];
    }

    public function execute(array $arguments): mixed
    {
        $query = $arguments['query'] ?? '';
        $client = new LaraPluginsClient;

        return $client->search($query);
    }
}
