<?php

namespace CodingSunshine\Ensemble\Console;

use CodingSunshine\Ensemble\Schema\SchemaWriter;
use CodingSunshine\Ensemble\Schema\TemplateRegistry;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TemplateCommand extends Command
{
    use Concerns\OutputsJson;

    protected function configure(): void
    {
        $this
            ->setName('template')
            ->setDescription('Browse and install starter templates')
            ->addArgument('subcommand', InputArgument::REQUIRED, 'Subcommand: list, show <name>, install <source>')
            ->addArgument('name', InputArgument::OPTIONAL, 'Template name or source (for show/install)')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Output path for install (default: ./ensemble.json)', './ensemble.json')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing file without prompting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $subcommand = $input->getArgument('subcommand');
        $name = $input->getArgument('name');

        return match ($subcommand) {
            'list'    => $this->runList($output),
            'show'    => $this->runShow($name, $output),
            'install' => $this->runInstall($name, $input, $output),
            default   => $this->unknownSubcommand($subcommand, $output),
        };
    }

    // -------------------------------------------------------------------------
    // Subcommand handlers
    // -------------------------------------------------------------------------

    private function runList(OutputInterface $output): int
    {
        $output->writeln('');
        $output->writeln('  <fg=cyan;options=bold>Ensemble Templates</>');
        $output->writeln('');
        $output->writeln('  <options=bold>Built-in Templates</>');

        foreach (TemplateRegistry::options() as $name => $description) {
            $output->writeln("    <fg=green>•</> <options=bold>{$name}</>  <fg=gray>{$description}</>");
        }

        $output->writeln('');

        // Show any cached external templates
        $cached = TemplateRegistry::cachedExternalTemplates();

        if (! empty($cached)) {
            $output->writeln('  <options=bold>Cached External Templates</>');

            foreach ($cached as $entry) {
                $age = $this->formatAge($entry['cached_at']);
                $output->writeln("    <fg=yellow>•</> {$entry['source']}  <fg=gray>(cached {$age})</>");
            }

            $output->writeln('');
        }

        $output->writeln('  <fg=gray>Usage: ensemble template show <name>  |  ensemble template install <source></>');
        $output->writeln('');

        return Command::SUCCESS;
    }

    private function runShow(string|null $name, OutputInterface $output): int
    {
        if ($name === null) {
            $output->writeln('  <fg=red>Error:</> Please provide a template name. Usage: template show <name>');

            return Command::FAILURE;
        }

        if (! TemplateRegistry::exists($name)) {
            $output->writeln("  <fg=red>Error:</> Unknown template \"{$name}\". Run 'template list' to see available templates.");

            return Command::FAILURE;
        }

        $schema = TemplateRegistry::load($name);
        $description = TemplateRegistry::options()[$name] ?? '';

        $output->writeln('');
        $output->writeln("  <fg=cyan;options=bold>{$name}</>  <fg=gray>{$description}</>");
        $output->writeln('');

        // App info
        $app = $schema['app'] ?? [];

        if (! empty($app)) {
            $stack = $app['stack'] ?? '—';
            $ui = $app['ui'] ?? '—';
            $output->writeln("  Stack: <fg=green>{$stack}</>   UI: <fg=green>{$ui}</>");
            $output->writeln('');
        }

        // Models summary
        $models = $schema['models'] ?? [];

        if (! empty($models)) {
            $output->writeln('  <options=bold>Models</>');

            foreach ($models as $modelName => $definition) {
                $fieldCount = isset($definition['fields']) ? count($definition['fields']) : 0;
                $relCount = isset($definition['relationships']) ? count($definition['relationships']) : 0;
                $details = [];

                if ($fieldCount > 0) {
                    $details[] = "{$fieldCount} fields";
                }

                if ($relCount > 0) {
                    $details[] = "{$relCount} relations";
                }

                $suffix = $details ? '  <fg=gray>('.implode(', ', $details).')</>' : '';
                $output->writeln("    <fg=yellow>•</> {$modelName}{$suffix}");
            }

            $output->writeln('');
        }

        $output->writeln("  <fg=gray>Install with: ensemble template install {$name}</>");
        $output->writeln('');

        return Command::SUCCESS;
    }

    private function runInstall(string|null $source, InputInterface $input, OutputInterface $output): int
    {
        if ($source === null) {
            $output->writeln('  <fg=red>Error:</> Please provide a template name or source. Usage: template install <name|github:user/repo>');

            return Command::FAILURE;
        }

        $outputPath = $input->getOption('output');
        $force = (bool) $input->getOption('force');

        // Check if the output file already exists
        if (file_exists($outputPath) && ! $force) {
            $output->writeln("  <fg=red>Error:</> File already exists: {$outputPath}. Use --force to overwrite.");

            return Command::FAILURE;
        }

        // Load the schema — built-in or external
        try {
            $schema = $this->resolveSchema($source);
        } catch (RuntimeException $e) {
            $output->writeln("  <fg=red>Error:</> ".$e->getMessage());

            return Command::FAILURE;
        }

        // Write the schema
        SchemaWriter::write($outputPath, $schema);

        $modelCount = count($schema['models'] ?? []);
        $appName = $schema['app']['name'] ?? $source;

        $output->writeln('');
        $output->writeln("  <fg=green>✓ Installed:</> {$appName} template → {$outputPath}");
        $output->writeln("    <fg=gray>{$modelCount} models ready. Run 'php artisan ensemble:build' to generate code.</>");
        $output->writeln('');

        return Command::SUCCESS;
    }

    private function unknownSubcommand(string $subcommand, OutputInterface $output): int
    {
        $output->writeln("  <fg=red>Error:</> Unknown subcommand '{$subcommand}'. Available: list, show, install");

        return Command::FAILURE;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve a template source (built-in name or external source) to a schema array.
     *
     * @return array<string, mixed>
     */
    protected function resolveSchema(string $source): array
    {
        // Built-in template
        if (TemplateRegistry::exists($source)) {
            return TemplateRegistry::load($source);
        }

        // External source (github:user/repo or URL)
        return TemplateRegistry::loadExternal($source);
    }

    private function formatAge(int $timestamp): string
    {
        $seconds = time() - $timestamp;

        if ($seconds < 60) {
            return 'just now';
        }

        if ($seconds < 3600) {
            $minutes = (int) ($seconds / 60);

            return "{$minutes}m ago";
        }

        if ($seconds < 86400) {
            $hours = (int) ($seconds / 3600);

            return "{$hours}h ago";
        }

        $days = (int) ($seconds / 86400);

        return "{$days}d ago";
    }
}
