<?php

namespace CodingSunshine\Ensemble\Console;

use CodingSunshine\Ensemble\Schema\SchemaWriter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DiffCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('diff')
            ->setDescription('Compare two ensemble.json schemas and show differences')
            ->addArgument('old', InputArgument::REQUIRED, 'Path to the old/original schema')
            ->addArgument('new', InputArgument::REQUIRED, 'Path to the new/updated schema');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $oldSchema = SchemaWriter::read($input->getArgument('old'));
        $newSchema = SchemaWriter::read($input->getArgument('new'));

        $output->writeln('');
        $output->writeln('  <fg=cyan;options=bold>Ensemble Diff</>');
        $output->writeln("  <fg=gray>{$input->getArgument('old')} → {$input->getArgument('new')}</>");
        $output->writeln('');

        $hasChanges = false;

        $hasChanges = $this->diffApp($oldSchema, $newSchema, $output) || $hasChanges;
        $hasChanges = $this->diffSection('Models', 'models', $oldSchema, $newSchema, $output, true) || $hasChanges;
        $hasChanges = $this->diffSection('Controllers', 'controllers', $oldSchema, $newSchema, $output) || $hasChanges;
        $hasChanges = $this->diffSection('Pages', 'pages', $oldSchema, $newSchema, $output) || $hasChanges;
        $hasChanges = $this->diffSection('Notifications', 'notifications', $oldSchema, $newSchema, $output) || $hasChanges;
        $hasChanges = $this->diffSection('Workflows', 'workflows', $oldSchema, $newSchema, $output) || $hasChanges;
        $hasChanges = $this->diffRecipes($oldSchema, $newSchema, $output) || $hasChanges;

        if (! $hasChanges) {
            $output->writeln('  <fg=gray>No differences found.</>');
        }

        $output->writeln('');

        return Command::SUCCESS;
    }

    protected function diffApp(array $old, array $new, OutputInterface $output): bool
    {
        $oldApp = $old['app'] ?? [];
        $newApp = $new['app'] ?? [];
        $changes = [];

        foreach (['name', 'stack', 'ui'] as $key) {
            $oldVal = $oldApp[$key] ?? null;
            $newVal = $newApp[$key] ?? null;

            if ($oldVal !== $newVal) {
                $oldDisplay = $oldVal ?? '(none)';
                $newDisplay = $newVal ?? '(none)';
                $changes[] = "    <fg=yellow>~</> {$key}: <fg=red>{$oldDisplay}</> → <fg=green>{$newDisplay}</>";
            }
        }

        if (empty($changes)) {
            return false;
        }

        $output->writeln('  <options=bold>Application</>');

        foreach ($changes as $line) {
            $output->writeln($line);
        }

        $output->writeln('');

        return true;
    }

    /**
     * Diff a keyed section (models, controllers, pages, notifications, workflows).
     */
    protected function diffSection(string $label, string $key, array $old, array $new, OutputInterface $output, bool $deepDiff = false): bool
    {
        $oldItems = $old[$key] ?? [];
        $newItems = $new[$key] ?? [];

        $added = array_diff_key($newItems, $oldItems);
        $removed = array_diff_key($oldItems, $newItems);
        $common = array_intersect_key($oldItems, $newItems);

        $modified = [];

        foreach ($common as $name => $oldDef) {
            $newDef = $newItems[$name];

            if ($oldDef !== $newDef) {
                $modified[$name] = $this->describeChanges($oldDef, $newDef, $deepDiff);
            }
        }

        if (empty($added) && empty($removed) && empty($modified)) {
            return false;
        }

        $output->writeln("  <options=bold>{$label}</>");

        foreach ($added as $name => $definition) {
            $output->writeln("    <fg=green>+</> {$name}");
        }

        foreach ($removed as $name => $definition) {
            $output->writeln("    <fg=red>-</> {$name}");
        }

        foreach ($modified as $name => $details) {
            $output->writeln("    <fg=yellow>~</> {$name}");

            foreach ($details as $detail) {
                $output->writeln("      {$detail}");
            }
        }

        $output->writeln('');

        return true;
    }

    protected function diffRecipes(array $old, array $new, OutputInterface $output): bool
    {
        $oldRecipes = $old['recipes'] ?? [];
        $newRecipes = $new['recipes'] ?? [];

        $oldNames = array_column($oldRecipes, 'name');
        $newNames = array_column($newRecipes, 'name');

        $added = array_diff($newNames, $oldNames);
        $removed = array_diff($oldNames, $newNames);

        if (empty($added) && empty($removed)) {
            return false;
        }

        $output->writeln('  <options=bold>Recipes</>');

        foreach ($added as $name) {
            $output->writeln("    <fg=green>+</> {$name}");
        }

        foreach ($removed as $name) {
            $output->writeln("    <fg=red>-</> {$name}");
        }

        $output->writeln('');

        return true;
    }

    /**
     * Describe what changed between two definitions.
     *
     * @return array<int, string>
     */
    protected function describeChanges(array $oldDef, array $newDef, bool $deep): array
    {
        $details = [];

        if (! $deep) {
            $details[] = '<fg=gray>changed</>';

            return $details;
        }

        $oldFields = $oldDef['fields'] ?? [];
        $newFields = $newDef['fields'] ?? [];

        foreach (array_diff_key($newFields, $oldFields) as $field => $type) {
            $details[] = "<fg=green>+ field:</> {$field} ({$type})";
        }

        foreach (array_diff_key($oldFields, $newFields) as $field => $type) {
            $details[] = "<fg=red>- field:</> {$field}";
        }

        foreach (array_intersect_key($oldFields, $newFields) as $field => $oldType) {
            $newType = $newFields[$field];

            if ($oldType !== $newType) {
                $details[] = "<fg=yellow>~ field:</> {$field}: <fg=red>{$oldType}</> → <fg=green>{$newType}</>";
            }
        }

        $oldRels = $oldDef['relationships'] ?? [];
        $newRels = $newDef['relationships'] ?? [];

        foreach (array_diff_key($newRels, $oldRels) as $rel => $type) {
            $details[] = "<fg=green>+ relation:</> {$rel} ({$type})";
        }

        foreach (array_diff_key($oldRels, $newRels) as $rel => $type) {
            $details[] = "<fg=red>- relation:</> {$rel}";
        }

        $oldSoftDeletes = $oldDef['softDeletes'] ?? false;
        $newSoftDeletes = $newDef['softDeletes'] ?? false;

        if ($oldSoftDeletes !== $newSoftDeletes) {
            $details[] = $newSoftDeletes
                ? '<fg=green>+ softDeletes</>'
                : '<fg=red>- softDeletes</>';
        }

        if (empty($details)) {
            $details[] = '<fg=gray>metadata changed</>';
        }

        return $details;
    }
}
