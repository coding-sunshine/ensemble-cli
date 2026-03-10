<?php

namespace CodingSunshine\Ensemble\Mcp\Tools;

use Symfony\Component\Process\Process;

class BuildProjectTool implements McpToolInterface
{
    public function name(): string
    {
        return 'build_project';
    }

    public function description(): string
    {
        return 'Run php artisan ensemble:build in the project directory.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_path' => [
                    'type' => 'string',
                    'description' => 'Path to the Laravel project root',
                ],
                'dry_run' => [
                    'type' => 'boolean',
                    'description' => 'If true, only list planned actions',
                ],
            ],
        ];
    }

    public function execute(array $arguments): mixed
    {
        $path = $arguments['project_path'] ?? getcwd();
        $projectPath = rtrim($path, '/\\');
        $dryRun = ! empty($arguments['dry_run']);

        $php = defined('PHP_BINARY') ? PHP_BINARY : 'php';
        $cmd = [$php, 'artisan', 'ensemble:build'];
        if ($dryRun) {
            $cmd[] = '--dry-run';
        }

        $process = new Process($cmd, $projectPath, null, null, 300.0);
        $process->run();

        $output = $process->getOutput().$process->getErrorOutput();

        return [
            'success' => $process->isSuccessful(),
            'exit_code' => $process->getExitCode(),
            'output' => $output,
        ];
    }
}
