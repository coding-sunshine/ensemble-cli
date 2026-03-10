<?php

namespace CodingSunshine\Ensemble\Mcp\Tools;

use CodingSunshine\Ensemble\Schema\SchemaValidator;

class ValidateSchemaTool implements McpToolInterface
{
    public function name(): string
    {
        return 'validate_schema';
    }

    public function description(): string
    {
        return 'Validate an ensemble schema (object or path). Returns errors and warnings.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'schema' => [
                    'type' => 'object',
                    'description' => 'Schema object to validate (if not using project_path)',
                ],
                'project_path' => [
                    'type' => 'string',
                    'description' => 'Path to project with ensemble.json to validate',
                ],
            ],
        ];
    }

    public function execute(array $arguments): mixed
    {
        $validator = new SchemaValidator;
        if (isset($arguments['project_path'])) {
            $path = rtrim($arguments['project_path'], '/\\').DIRECTORY_SEPARATOR.'ensemble.json';
            $schema = \CodingSunshine\Ensemble\Schema\SchemaWriter::read($path);
        } elseif (isset($arguments['schema']) && is_array($arguments['schema'])) {
            $schema = $arguments['schema'];
        } else {
            throw new \InvalidArgumentException('Provide schema or project_path');
        }
        $validator->validate($schema);

        return [
            'valid' => $validator->errors() === [],
            'errors' => $validator->errors(),
            'warnings' => $validator->warnings(),
        ];
    }
}
