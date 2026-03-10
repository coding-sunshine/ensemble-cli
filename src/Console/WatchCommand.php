<?php

namespace CodingSunshine\Ensemble\Console;

use CodingSunshine\Ensemble\Schema\SchemaValidator;
use CodingSunshine\Ensemble\Schema\SchemaWriter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Watch a schema file from outside a Laravel project (e.g. external editor).
 * On change: validate; optionally run dry-run build.
 */
class WatchCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('watch')
            ->setDescription('Watch a schema file and validate (and optionally dry-run build) on change')
            ->addArgument('schema', InputArgument::OPTIONAL, 'Path to ensemble.json (or directory containing it)', 'ensemble.json')
            ->addOption('interval', 'i', InputOption::VALUE_REQUIRED, 'Polling interval in milliseconds when fswatch/inotify not available', '1000')
            ->addOption('debounce', null, InputOption::VALUE_REQUIRED, 'Debounce time in ms before acting on a change', '500')
            ->addOption('auto-build', null, InputOption::VALUE_NONE, 'Run php artisan ensemble:build --dry-run in project dir on change')
            ->addOption('project', 'p', InputOption::VALUE_REQUIRED, 'Path to Laravel project root (for --auto-build)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Allow config to supply defaults for schema path, interval, debounce, auto-build
        $config = new \CodingSunshine\Ensemble\Config\ConfigStore();

        $schemaArg = $input->getArgument('schema');
        // If the user passed the default value ('ensemble.json') and config has a path, prefer config
        if ($schemaArg === 'ensemble.json' && $config->get('watch.schema_path')) {
            $schemaArg = $config->get('watch.schema_path');
        }

        $schemaPath = $this->resolveSchemaPath($schemaArg);
        if ($schemaPath === null || ! is_file($schemaPath)) {
            $output->writeln('<error>Schema file not found.</error>');
            $output->writeln('  Tip: Run <comment>ensemble config set watch.schema_path /path/to/ensemble.json</comment> to persist the path.');
            return self::FAILURE;
        }

        $intervalMs = (int) ($input->getOption('interval') ?? $config->get('watch.interval_ms', 1000));
        $debounceMs = (int) ($input->getOption('debounce') ?? $config->get('watch.debounce_ms', 500));
        $autoBuild  = $input->getOption('auto-build') ?: (bool) $config->get('watch.auto_build', false);
        $projectPath = $input->getOption('project') ?? $config->get('watch.project_path') ?? dirname($schemaPath);

        $output->writeln("Watching <info>{$schemaPath}</info> (debounce: {$debounceMs}ms)");
        if ($autoBuild) {
            $output->writeln("Auto dry-run build in <info>{$projectPath}</info>");
        }

        $lastMtime = filemtime($schemaPath);
        $lastAction = 0.0;

        while (true) {
            $mtime = @filemtime($schemaPath);
            if ($mtime !== false && $mtime > $lastMtime) {
                $now = microtime(true) * 1000;
                if ($now - $lastAction >= $debounceMs) {
                    $lastAction = $now;
                    $lastMtime = $mtime;
                    usleep((int) ($debounceMs * 1000));
                    $this->onChange($schemaPath, $projectPath, $autoBuild, $output);
                }
            } else {
                if ($mtime !== false) {
                    $lastMtime = $mtime;
                }
            }
            usleep((int) ($intervalMs * 1000));
        }
    }

    private function resolveSchemaPath(string $path): ?string
    {
        if (is_file($path)) {
            return realpath($path) ?: $path;
        }
        if (is_dir($path)) {
            $candidate = rtrim($path, '/\\').DIRECTORY_SEPARATOR.'ensemble.json';
            if (is_file($candidate)) {
                return realpath($candidate) ?: $candidate;
            }
        }
        return null;
    }

    private function onChange(string $schemaPath, string $projectPath, bool $autoBuild, OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('  <fg=cyan>Change detected</> '.date('H:i:s'));

        try {
            $schema = SchemaWriter::readLoose($schemaPath);
            $validator = new SchemaValidator;
            $validator->validate($schema);
            $errors = $validator->errors();
            $warnings = $validator->warnings();

            if ($errors !== []) {
                $output->writeln('  <error>Validation errors:</error>');
                foreach ($errors as $e) {
                    $output->writeln('    - '.$e);
                }
            } else {
                $output->writeln('  <info>Schema valid.</info>');
                if ($warnings !== []) {
                    foreach ($warnings as $w) {
                        $output->writeln('  <comment>Warning:</comment> '.$w);
                    }
                }
            }

            if ($autoBuild && $errors === [] && is_dir($projectPath)) {
                $php = defined('PHP_BINARY') ? PHP_BINARY : 'php';
                $process = new Process([$php, 'artisan', 'ensemble:build', '--dry-run', '--no-interaction'], $projectPath, null, null, 60.0);
                $process->run();
                $output->writeln($process->getOutput());
                if ($process->getErrorOutput()) {
                    $output->writeln('<comment>'.$process->getErrorOutput().'</comment>');
                }
            }
        } catch (\Throwable $e) {
            $output->writeln('  <error>'.$e->getMessage().'</error>');
        }
        $output->writeln('');
    }
}
