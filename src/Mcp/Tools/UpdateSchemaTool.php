<?php

namespace CodingSunshine\Ensemble\Mcp\Tools;

use CodingSunshine\Ensemble\Schema\SchemaWriter;

class UpdateSchemaTool implements McpToolInterface
{
    public function name(): string
    {
        return 'update_schema';
    }

    public function description(): string
    {
        return 'Write the ensemble.json schema to a project directory.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_path' => [
                    'type' => 'string',
                    'description' => 'Path to the project root',
                ],
                'schema' => [
                    'type' => 'object',
                    'description' => 'The full schema object to write',
                ],
            ],
            'required' => ['schema'],
        ];
    }

    public function execute(array $arguments): mixed
    {
        $path = $arguments['project_path'] ?? getcwd();
        $schemaPath = rtrim($path, '/\\').DIRECTORY_SEPARATOR.'ensemble.json';
        $schema = $arguments['schema'];
        if (! is_array($schema)) {
            throw new \InvalidArgumentException('schema must be an object');
        }
        SchemaWriter::write($schemaPath, $schema);

        return ['ok' => true, 'path' => $schemaPath];
    }
}
