<?php

namespace CodingSunshine\Ensemble\Console\Concerns;

use CodingSunshine\Ensemble\Scaffold\StarterKitResolver;
use Symfony\Component\Console\Output\OutputInterface;

trait DisplaysDryRun
{
    /**
     * Display a dry-run summary of what would happen with the given schema.
     *
     * @param  array<string, mixed>  $schema
     */
    protected function displayDryRun(OutputInterface $output, array $schema, string $context = 'new'): void
    {
        $output->writeln('');
        $output->writeln('  <fg=cyan;options=bold>Ensemble Dry Run</> — preview of planned operations');
        $output->writeln('');

        $app = $schema['app'] ?? [];
        $stack = $app['stack'] ?? null;
        $starterKit = $stack ? StarterKitResolver::resolve($stack) : null;

        $output->writeln('  <options=bold>Application</>');
        $output->writeln('    Name:        <fg=green>'.($app['name'] ?? '(from argument)').'</>');

        if ($stack) {
            $output->writeln("    Stack:       <fg=green>{$stack}</>");
        }

        if (isset($app['ui'])) {
            $output->writeln("    UI:          <fg=green>{$app['ui']}</>");
        }

        if ($starterKit && $context === 'new') {
            $output->writeln("    Starter Kit: <fg=green>{$starterKit}</>");
        }

        $output->writeln('');

        $sections = [
            'models' => 'Models',
            'controllers' => 'Controllers',
            'pages' => 'Pages',
            'notifications' => 'Notifications',
            'workflows' => 'Workflows',
        ];

        $output->writeln('  <options=bold>Schema Contents</>');

        foreach ($sections as $key => $label) {
            $items = $schema[$key] ?? [];
            $count = is_array($items) ? count($items) : 0;

            if ($count > 0) {
                $names = implode(', ', array_keys($items));
                $output->writeln("    {$label}: <fg=green>{$count}</> <fg=gray>({$names})</>");
            }
        }

        $output->writeln('');

        $recipes = $schema['recipes'] ?? [];
        $packages = array_filter(array_column($recipes, 'package'));

        if (! empty($packages)) {
            $output->writeln('  <options=bold>Packages to Install</>');
            $output->writeln('    <fg=gray>coding-sunshine/ensemble</> (dev)');

            foreach ($packages as $package) {
                $output->writeln("    <fg=gray>{$package}</>");
            }

            $output->writeln('');
        }

        if ($context === 'new') {
            $output->writeln('  <options=bold>Steps</>');
            $output->writeln('    1. <fg=gray>Create Laravel project with starter kit</>');
            $output->writeln('    2. <fg=gray>Configure environment and database</>');
            $output->writeln('    3. <fg=gray>Install Ensemble package</>');
            $output->writeln('    4. <fg=gray>Write ensemble.json to project root</>');

            if (! empty($packages)) {
                $output->writeln('    5. <fg=gray>Install recipe packages</>');
                $output->writeln('    6. <fg=gray>Run ensemble:build, migrate, seed</>');
            } else {
                $output->writeln('    5. <fg=gray>Run ensemble:build, migrate, seed</>');
            }

            $output->writeln('');
        } elseif ($context === 'init') {
            $output->writeln('  <options=bold>Steps</>');
            $output->writeln('    1. <fg=gray>Write ensemble.json to project root</>');

            if (! empty($packages)) {
                $output->writeln('    2. <fg=gray>Install recipe packages</>');
                $output->writeln('    3. <fg=gray>Install Ensemble package + run build</>');
            } else {
                $output->writeln('    2. <fg=gray>Install Ensemble package + run build</>');
            }

            $output->writeln('');
        }

        $output->writeln('  <fg=yellow>No changes were made.</> Remove <fg=white>--dry-run</> to execute.');
        $output->writeln('');
    }
}
