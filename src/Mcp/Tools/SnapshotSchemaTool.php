<?php

namespace CodingSunshine\Ensemble\Mcp\Tools;

use Symfony\Component\Process\Process;

class SnapshotSchemaTool implements McpToolInterface
{
    public function name(): string
    {
        return 'snapshot_schema';
    }

    public function description(): string
    {
        return 'Run php artisan ensemble:snapshot to save a schema snapshot (Laravel project).';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_path' => ['type' => 'string', 'description' => 'Path to the Laravel project root'],
            ],
        ];
    }

    public function execute(array $arguments): mixed
    {
        $path = $arguments['project_path'] ?? getcwd();
        $projectPath = rtrim($path, '/\\');

        $php = defined('PHP_BINARY') ? PHP_BINARY : 'php';
        $process = new Process([$php, 'artisan', 'ensemble:snapshot', '--no-interaction'], $projectPath, null, null, 30.0);
        $process->run();

        return [
            'success' => $process->isSuccessful(),
            'exit_code' => $process->getExitCode(),
            'output' => $process->getOutput().$process->getErrorOutput(),
        ];
    }
}
