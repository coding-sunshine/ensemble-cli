<?php

namespace CodingSunshine\Ensemble\Mcp\Tools;

use Symfony\Component\Process\Process;

class AppendModelTool implements McpToolInterface
{
    public function name(): string
    {
        return 'append_model';
    }

    public function description(): string
    {
        return 'Run php artisan ensemble:append to add a model to the schema (Laravel project must have ensemble installed).';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_path' => ['type' => 'string', 'description' => 'Path to the Laravel project root'],
                'name' => ['type' => 'string', 'description' => 'Model name to add'],
            ],
            'required' => ['name'],
        ];
    }

    public function execute(array $arguments): mixed
    {
        $path = $arguments['project_path'] ?? getcwd();
        $projectPath = rtrim($path, '/\\');
        $name = $arguments['name'] ?? '';
        if (! is_string($name) || $name === '') {
            throw new \InvalidArgumentException('name is required');
        }

        $php = defined('PHP_BINARY') ? PHP_BINARY : 'php';
        $process = new Process([$php, 'artisan', 'ensemble:append', $name, '--no-interaction'], $projectPath, null, null, 60.0);
        $process->run();

        return [
            'success' => $process->isSuccessful(),
            'exit_code' => $process->getExitCode(),
            'output' => $process->getOutput().$process->getErrorOutput(),
        ];
    }
}
