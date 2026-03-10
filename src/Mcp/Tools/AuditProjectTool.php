<?php

namespace CodingSunshine\Ensemble\Mcp\Tools;

/**
 * Read .ensemble/file-hashes.json and compare with current files. Returns modified and deleted paths.
 */
class AuditProjectTool implements McpToolInterface
{
    public function name(): string
    {
        return 'audit_project';
    }

    public function description(): string
    {
        return 'List generated files that have been modified or deleted since the last build (reads .ensemble/file-hashes.json).';
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
        $base = rtrim($path, '/\\');
        $hashFile = $base.DIRECTORY_SEPARATOR.'.ensemble'.DIRECTORY_SEPARATOR.'file-hashes.json';

        if (! is_file($hashFile)) {
            return ['modified' => [], 'deleted' => [], 'message' => 'No file-hashes.json found. Run ensemble:build first.'];
        }

        $hashes = json_decode(file_get_contents($hashFile), true);
        if (! is_array($hashes)) {
            return ['modified' => [], 'deleted' => [], 'message' => 'Invalid file-hashes.json'];
        }

        $modified = [];
        $deleted = [];

        foreach ($hashes as $relPath => $storedHash) {
            $absPath = $base.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relPath);

            if (! file_exists($absPath)) {
                $deleted[] = $relPath;
                continue;
            }

            $currentHash = 'sha256:'.hash('sha256', file_get_contents($absPath));
            if ($currentHash !== $storedHash) {
                $modified[] = $relPath;
            }
        }

        return [
            'modified' => $modified,
            'deleted' => $deleted,
        ];
    }
}
