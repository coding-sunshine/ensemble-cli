<?php

namespace CodingSunshine\Ensemble\Console;

use CodingSunshine\Ensemble\AI\SchemaPatcher;
use CodingSunshine\Ensemble\AI\SchemaJsonSchema;
use CodingSunshine\Ensemble\Schema\SchemaValidator;
use CodingSunshine\Ensemble\Schema\SchemaWriter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\textarea;

class UpdateCommand extends Command
{
    use Concerns\OutputsJson;
    use Concerns\ResolvesAIProvider;

    protected function configure(): void
    {
        $this
            ->setName('update')
            ->setDescription('Update an existing project schema with a natural language prompt')
            ->addArgument('directory', InputArgument::REQUIRED, 'Path to the Laravel project directory')
            ->addOption('prompt', null, InputOption::VALUE_REQUIRED, 'Natural language description of the change to make')
            ->addOption('build', null, InputOption::VALUE_NONE, 'Run php artisan ensemble:build after updating the schema')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show diff only, do not write changes')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Skip confirmation and apply changes immediately')
            ->addOption('provider', null, InputOption::VALUE_REQUIRED, 'AI provider (anthropic, openai, openrouter, ollama)')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key for the AI provider')
            ->addOption('model', null, InputOption::VALUE_REQUIRED, 'AI model to use');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $directory  = rtrim($input->getArgument('directory'), '/\\');
        $schemaPath = $directory.'/ensemble.json';
        $isDryRun   = (bool) $input->getOption('dry-run');
        $applyFlag  = (bool) $input->getOption('apply');

        if (! file_exists($schemaPath)) {
            $output->writeln('');
            $output->writeln("  <fg=red>Error:</> No ensemble.json found at: {$schemaPath}");
            $output->writeln('');

            return Command::FAILURE;
        }

        [$fullSchema, $readError] = $this->readSchema($schemaPath);

        if ($fullSchema === null) {
            $output->writeln('');
            $output->writeln("  <fg=red>Error:</> " . ($readError ?? "Could not read schema file: {$schemaPath}"));
            $output->writeln('');

            return Command::FAILURE;
        }

        $userPrompt = $input->getOption('prompt') ?: textarea(
            label: 'What changes would you like to make?',
            placeholder: 'e.g. Add team collaboration with Team model, invite members, and assign roles',
            required: true,
        );

        $provider = $this->resolveProvider($input);
        $patcher = new SchemaPatcher();
        $systemPrompt = $this->loadSystemPrompt();
        $patchDef = SchemaJsonSchema::patchDefinition();

        $userMessage = $patcher->buildDeltaPrompt($fullSchema, $userPrompt);

        $patch = spin(
            message: 'Generating schema patch...',
            callback: fn () => $provider->completeStructured($systemPrompt, $userMessage, $patchDef),
        );

        $patched = $patcher->applyPatch($fullSchema, $patch);

        // Validate; retry once on failure
        $validator = new SchemaValidator();
        $validator->validate($patched);
        $errors = $validator->errors();

        if (! empty($errors)) {
            $errorList = implode("\n- ", $errors);
            $retryMessage = $patcher->buildDeltaPrompt($fullSchema, $userPrompt)
                ."\n\nThe previous attempt produced an invalid schema. Errors:\n- {$errorList}\n\nPlease fix these issues.";

            $patch = spin(
                message: 'Retrying with validation errors...',
                callback: fn () => $provider->completeStructured($systemPrompt, $retryMessage, $patchDef),
            );

            $patched = $patcher->applyPatch($fullSchema, $patch);

            $validator = new SchemaValidator();
            $validator->validate($patched);
            $errors = $validator->errors();
        }

        $diff = $patcher->diff($fullSchema, $patched);

        $output->writeln('');
        $output->writeln('  <fg=cyan;options=bold>Ensemble Update</>');
        $output->writeln("  <fg=gray>Prompt: {$userPrompt}</>");
        $output->writeln('');

        if (! empty($errors)) {
            $output->writeln('  <fg=red;options=bold>Validation Errors — patch could not be fully resolved:</>');
            foreach ($errors as $error) {
                $output->writeln("    <fg=red>✗</> {$error}");
            }
            $output->writeln('');
            $output->writeln('  <fg=yellow>No changes written. Fix the prompt or run ensemble:validate to inspect the schema.</>');
            $output->writeln('');

            return Command::FAILURE;
        }

        $this->displayDiff($diff, $output);

        if ($isDryRun) {
            $output->writeln('  <fg=gray>Dry run — no changes written.</>');
            $output->writeln('');

            return Command::SUCCESS;
        }

        $shouldWrite = $applyFlag || confirm(
            label: 'Apply these changes to your schema?',
            default: true,
        );

        if (! $shouldWrite) {
            $output->writeln('  <fg=gray>No changes written.</>');
            $output->writeln('');

            return Command::SUCCESS;
        }

        SchemaWriter::write($schemaPath, $patched);
        $output->writeln("  <fg=green>✓ Schema updated:</> {$schemaPath}");
        $output->writeln('');

        if ($input->getOption('build')) {
            return $this->runBuild($directory, $output);
        }

        return Command::SUCCESS;
    }

    /**
     * Read and decode a schema file. Validates JSON validity and version compatibility
     * but skips structural validation so the AI can patch broken schemas.
     * Returns [schema, null] on success or [null, errorMessage] on failure.
     *
     * @return array{0: array<string, mixed>|null, 1: string|null}
     */
    protected function readSchema(string $path): array
    {
        try {
            return [SchemaWriter::readLoose($path), null];
        } catch (\RuntimeException $e) {
            return [null, $e->getMessage()];
        }
    }

    /**
     * Load the system prompt from the bundled stub.
     */
    protected function loadSystemPrompt(): string
    {
        $stub = __DIR__.'/../../stubs/ai-patch-prompt.md';

        if (file_exists($stub)) {
            return file_get_contents($stub);
        }

        return 'You are a Laravel Ensemble schema editor. Return only a partial JSON patch containing the sections to add or modify. Do not include unchanged sections.';
    }

    /**
     * Run php artisan ensemble:build in the target directory.
     */
    protected function runBuild(string $directory, OutputInterface $output): int
    {
        $php = (new PhpExecutableFinder())->find(false) ?: 'php';

        $process = new Process([$php, 'artisan', 'ensemble:build'], $directory);
        $process->setTimeout(300);
        $process->run(function (string $type, string $buffer) use ($output): void {
            $output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $output->writeln('');
            $output->writeln('  <fg=red>ensemble:build failed.</>');
            $output->writeln('');

            return Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln('  <fg=green>✓ Build complete.</>');
        $output->writeln('');

        return Command::SUCCESS;
    }

    /**
     * Display the diff in a human-readable format.
     *
     * @param  array{added: array<string, mixed>, modified: array<string, mixed>, removed: array<string, mixed>}  $diff
     */
    protected function displayDiff(array $diff, OutputInterface $output): void
    {
        $hasChanges = false;

        if (! empty($diff['added'])) {
            $output->writeln('  <options=bold>Added</>');
            foreach (array_keys($diff['added']) as $key) {
                $output->writeln("    <fg=green>+</> {$key}");
            }
            $output->writeln('');
            $hasChanges = true;
        }

        if (! empty($diff['modified'])) {
            $output->writeln('  <options=bold>Modified</>');
            foreach (array_keys($diff['modified']) as $key) {
                $output->writeln("    <fg=yellow>~</> {$key}");
            }
            $output->writeln('');
            $hasChanges = true;
        }

        if (! empty($diff['removed'])) {
            $output->writeln('  <options=bold>Removed</>');
            foreach (array_keys($diff['removed']) as $key) {
                $output->writeln("    <fg=red>-</> {$key}");
            }
            $output->writeln('');
            $hasChanges = true;
        }

        if (! $hasChanges) {
            $output->writeln('  <fg=gray>No schema changes detected.</>');
            $output->writeln('');
        }
    }
}
