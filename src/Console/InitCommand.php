<?php

namespace CodingSunshine\Ensemble\Console;

use CodingSunshine\Ensemble\AI\ConversationEngine;
use CodingSunshine\Ensemble\Schema\SchemaWriter;
use CodingSunshine\Ensemble\Schema\TemplateRegistry;
use Illuminate\Support\Composer;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ProcessUtils;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

class InitCommand extends Command
{
    use Concerns\ConfiguresPrompts;
    use Concerns\DisplaysDryRun;
    use Concerns\ResolvesAIProvider;

    /**
     * Configure the command options.
     */
    protected function configure(): void
    {
        $this
            ->setName('init')
            ->setDescription('Add Ensemble AI scaffolding to an existing Laravel project')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Path to the Laravel project', '.')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Path to an existing ensemble.json schema file')
            ->addOption('template', 't', InputOption::VALUE_REQUIRED, 'Use a bundled template: '.implode(', ', TemplateRegistry::names()))
            ->addOption('provider', null, InputOption::VALUE_REQUIRED, 'AI provider: anthropic, openai, openrouter, ollama')
            ->addOption('model', null, InputOption::VALUE_REQUIRED, 'Override the default AI model for the chosen provider')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key for the AI provider')
            ->addOption('ai-budget', null, InputOption::VALUE_REQUIRED, 'AI budget level for ensemble:build (none, low, medium, high)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would happen without making any changes');
    }

    /**
     * Interact with the user before validating the input.
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        parent::interact($input, $output);

        $this->configurePrompts($input, $output);

        $output->writeln('');
        $output->writeln('  <fg=cyan;options=bold>Laravel Ensemble</> — Init Mode');
        $output->writeln('  <fg=gray>Add AI scaffolding to an existing Laravel project</>');
        $output->writeln('');
    }

    /**
     * Execute the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $directory = realpath($input->getOption('path')) ?: $input->getOption('path');

        $this->ensureIsLaravelProject($directory);

        $schema = $this->resolveSchema($input, $output);

        if ($schema === null) {
            return Command::SUCCESS;
        }

        if ($input->getOption('dry-run')) {
            $this->displayDryRun($output, $schema, 'init');

            return Command::SUCCESS;
        }

        $schemaPath = $directory.'/ensemble.json';

        if (file_exists($schemaPath)) {
            $overwrite = confirm(
                label: 'An ensemble.json already exists. Overwrite it?',
                default: false,
            );

            if (! $overwrite) {
                warning('Aborted. Existing ensemble.json was not modified.');

                return Command::SUCCESS;
            }
        }

        SchemaWriter::write($schemaPath, $schema);
        info('Wrote ensemble.json to project root.');

        $this->installRecipePackages($directory, $schema, $input, $output);
        $this->installEnsemblePackage($directory, $input, $output);

        return Command::SUCCESS;
    }

    /**
     * Resolve schema from --from file or AI conversation.
     *
     * @return array<string, mixed>|null
     */
    protected function resolveSchema(InputInterface $input, ?OutputInterface $output = null): ?array
    {
        if ($fromPath = $input->getOption('from')) {
            $schema = SchemaWriter::read($fromPath);
            info("Loaded schema from {$fromPath}");

            return $schema;
        }

        if ($templateName = $input->getOption('template')) {
            $schema = TemplateRegistry::load($templateName);
            info("Loaded \"{$templateName}\" template.");

            return $schema;
        }

        $provider = $this->resolveProvider($input);
        $engine = new ConversationEngine($provider, $output);

        return $engine->run();
    }

    /**
     * Verify the target directory contains a Laravel project.
     */
    protected function ensureIsLaravelProject(string $directory): void
    {
        if (! file_exists($directory.'/artisan') || ! file_exists($directory.'/composer.json')) {
            throw new RuntimeException(
                "No Laravel project found at [{$directory}]. Make sure you're in a Laravel project directory or use --path."
            );
        }

        $composerJson = json_decode(file_get_contents($directory.'/composer.json'), true);
        $requires = $composerJson['require'] ?? [];

        if (! isset($requires['laravel/framework'])) {
            throw new RuntimeException(
                "The project at [{$directory}] does not appear to be a Laravel application (laravel/framework not in require)."
            );
        }
    }

    /**
     * Install Composer packages referenced by the schema recipes.
     *
     * @param  array<string, mixed>  $schema
     */
    protected function installRecipePackages(string $directory, array $schema, InputInterface $input, OutputInterface $output): void
    {
        $recipes = $schema['recipes'] ?? [];

        if (empty($recipes)) {
            return;
        }

        $packages = array_filter(array_column($recipes, 'package'));

        if (empty($packages)) {
            return;
        }

        $composer = new Composer(new Filesystem(), $directory);
        $composerBinary = implode(' ', $composer->findComposer());
        $packageList = implode(' ', $packages);

        $this->runShellCommands(
            [$composerBinary.' require '.$packageList],
            $input,
            $output,
            workingPath: $directory,
        );
    }

    /**
     * Install the coding-sunshine/ensemble companion package and run build.
     */
    protected function installEnsemblePackage(string $directory, InputInterface $input, OutputInterface $output): void
    {
        try {
            $composer = new Composer(new Filesystem(), $directory);
            $composerBinary = implode(' ', $composer->findComposer());
            $phpBinary = $this->phpBinary();

            $this->runShellCommands(
                [$composerBinary.' require coding-sunshine/ensemble --dev'],
                $input,
                $output,
                workingPath: $directory,
            );

            $budgetFlag = $input->getOption('ai-budget')
                ? ' --budget='.$input->getOption('ai-budget')
                : '';

            $this->runShellCommands([
                $phpBinary.' artisan ensemble:build --no-interaction'.$budgetFlag,
            ], $input, $output, workingPath: $directory);

            $this->runShellCommands([
                $phpBinary.' artisan migrate --force',
            ], $input, $output, workingPath: $directory);

            info('Ensemble scaffolding complete.');
        } catch (Throwable $exception) {
            warning('Could not install coding-sunshine/ensemble. You can install it later with:');
            $output->writeln('  <options=bold>composer require coding-sunshine/ensemble --dev</>');
            $output->writeln('  <options=bold>php artisan ensemble:build</>');
            $output->writeln('');
        }
    }

    protected function phpBinary(): string
    {
        $phpBinary = function_exists('Illuminate\Support\php_binary')
            ? \Illuminate\Support\php_binary()
            : (new PhpExecutableFinder)->find(false);

        return $phpBinary !== false
            ? ProcessUtils::escapeArgument($phpBinary)
            : 'php';
    }

    /**
     * Run shell commands in sequence.
     *
     * @param  array<int, string>  $commands
     */
    protected function runShellCommands(array $commands, InputInterface $input, OutputInterface $output, ?string $workingPath = null): Process
    {
        $process = Process::fromShellCommandline(implode(' && ', $commands), $workingPath, null, null, null);

        if (Process::isTtySupported()) {
            try {
                $process->setTty(true);
            } catch (RuntimeException $e) {
                $output->writeln('  <bg=yellow;fg=black> WARN </> '.$e->getMessage().PHP_EOL);
            }
        }

        $process->run(function ($type, $line) use ($output) {
            $output->write('    '.$line);
        });

        return $process;
    }
}
