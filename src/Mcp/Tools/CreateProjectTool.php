<?php

namespace CodingSunshine\Ensemble\Mcp\Tools;

use Symfony\Component\Process\Process;

/**
 * Create a new Laravel project via ensemble new with -n (no interaction).
 */
class CreateProjectTool implements McpToolInterface
{
    public function name(): string
    {
        return 'create_project';
    }

    public function description(): string
    {
        return 'Create a new Laravel application with optional Ensemble scaffolding. Always runs non-interactively; pass all options via arguments.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name'        => ['type' => 'string', 'description' => 'Project name (directory)'],
                'path'        => ['type' => 'string', 'description' => 'Parent path to create project in'],
                'database'    => ['type' => 'string', 'description' => 'Database driver: mysql, pgsql, sqlite, etc.'],
                'git'         => ['type' => 'boolean', 'description' => 'Initialize git'],
                'template'    => ['type' => 'string', 'description' => 'Bundled template name: saas, blog, ecommerce, crm, api, project-management, marketplace, booking, inventory, helpdesk, lms, social'],
                'schema_path' => ['type' => 'string', 'description' => 'Absolute path to an existing ensemble.json to use instead of AI generation'],
                'stack'       => ['type' => 'string', 'description' => 'Frontend stack: blade, livewire, inertia-react, inertia-vue'],
            ],
            'required' => ['name'],
        ];
    }

    public function execute(array $arguments): mixed
    {
        $name = $arguments['name'] ?? null;
        if (! is_string($name) || $name === '') {
            throw new \InvalidArgumentException('name is required');
        }

        $basePath = $arguments['path'] ?? getcwd();
        $php = defined('PHP_BINARY') ? PHP_BINARY : 'php';
        $cmd = [
            $php,
            $this->ensembleBinary(),
            'new',
            $name,
            '--no-interaction',
        ];

        if (! empty($arguments['database']) && is_string($arguments['database'])) {
            $cmd[] = '--database='.$arguments['database'];
        }
        if (! empty($arguments['git'])) {
            $cmd[] = '--git';
        }
        if (! empty($arguments['template']) && is_string($arguments['template'])) {
            $cmd[] = '--template='.$arguments['template'];
        }
        if (! empty($arguments['schema_path']) && is_string($arguments['schema_path'])) {
            $cmd[] = '--from='.$arguments['schema_path'];
        }
        if (! empty($arguments['stack']) && is_string($arguments['stack'])) {
            $cmd[] = '--stack='.$arguments['stack'];
        }

        $process = new Process($cmd, $basePath, null, null, 600.0);
        $process->run();

        $output = $process->getOutput().$process->getErrorOutput();

        return [
            'success' => $process->isSuccessful(),
            'exit_code' => $process->getExitCode(),
            'output' => $output,
            'path' => rtrim($basePath, '/\\').DIRECTORY_SEPARATOR.$name,
        ];
    }

    private function ensembleBinary(): string
    {
        $bin = __DIR__.'/../../bin/ensemble';
        if (file_exists($bin)) {
            return $bin;
        }
        $phar = __DIR__.'/../../../ensemble.phar';
        if (file_exists($phar)) {
            return $phar;
        }
        return 'ensemble';
    }
}
