<?php

namespace CodingSunshine\Ensemble\Mcp\Tools;

use CodingSunshine\Ensemble\Schema\SchemaWriter;

class GetSchemaTool implements McpToolInterface
{
    public function name(): string
    {
        return 'get_schema';
    }

    public function description(): string
    {
        return 'Read the ensemble.json schema from a project directory.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_path' => [
                    'type' => 'string',
                    'description' => 'Path to the project root (default: current working directory)',
                ],
            ],
        ];
    }

    public function execute(array $arguments): mixed
    {
        $path = $arguments['project_path'] ?? getcwd();
        $schemaPath = rtrim($path, '/\\').DIRECTORY_SEPARATOR.'ensemble.json';

        return SchemaWriter::read($schemaPath);
    }
}
