<?php

namespace CodingSunshine\Ensemble\Console;

use CodingSunshine\Ensemble\AI\SchemaPatcher;
use CodingSunshine\Ensemble\AI\SchemaJsonSchema;
use CodingSunshine\Ensemble\Schema\SchemaValidator;
use CodingSunshine\Ensemble\Schema\SchemaWriter;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\spin;

class AiCommand extends Command
{
    use Concerns\OutputsJson;
    use Concerns\ResolvesAIProvider;

    protected function configure(): void
    {
        $this
            ->setName('ai')
            ->setDescription('Modify your schema with a natural language prompt')
            ->addArgument('prompt', InputArgument::REQUIRED, 'Natural language description of the change to make')
            ->addOption('schema', null, InputOption::VALUE_REQUIRED, 'Path to the ensemble.json file', './ensemble.json')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Skip confirmation and apply changes immediately')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show diff only, do not write changes')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output machine-readable JSON')
            ->addOption('provider', null, InputOption::VALUE_REQUIRED, 'AI provider (anthropic, openai, openrouter, ollama)')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key for the AI provider')
            ->addOption('model', null, InputOption::VALUE_REQUIRED, 'AI model to use');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $schemaPath = $input->getOption('schema');
        $userPrompt = $input->getArgument('prompt');
        $isDryRun   = (bool) $input->getOption('dry-run');
        $applyFlag  = (bool) $input->getOption('apply');
        $jsonOutput = (bool) $input->getOption('json');

        if (! file_exists($schemaPath)) {
            return $this->fail($output, "Schema file not found: {$schemaPath}", $jsonOutput);
        }

        $fullSchema = $this->readSchema($schemaPath);

        if ($fullSchema === null) {
            return $this->fail($output, "Invalid JSON in schema file: {$schemaPath}", $jsonOutput);
        }

        $provider = $this->resolveProvider($input);
        $patcher  = new SchemaPatcher();
        $systemPrompt = $this->loadSystemPrompt();
        $patchDef = SchemaJsonSchema::patchDefinition();

        $userMessage = $patcher->buildDeltaPrompt($fullSchema, $userPrompt);

        $patch = spin(
            message: 'Generating schema patch...',
            callback: fn () => $provider->completeStructured($systemPrompt, $userMessage, $patchDef),
        );

        $patched = $patcher->applyPatch($fullSchema, $patch);

        // Validate result; retry once on failure
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

        if ($jsonOutput) {
            $success = empty($errors);
            $output->writeln(json_encode([
                'success'  => $success,
                'added'    => $diff['added'],
                'modified' => $diff['modified'],
                'removed'  => $diff['removed'],
                'schema'   => $patched,
                'errors'   => $errors,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $success ? Command::SUCCESS : Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln('  <fg=cyan;options=bold>Ensemble AI Patch</>');
        $output->writeln("  <fg=gray>Prompt: {$userPrompt}</>");
        $output->writeln('');

        if (! empty($errors)) {
            $output->writeln('  <fg=red;options=bold>Validation Errors (patch may be incomplete)</>');
            foreach ($errors as $error) {
                $output->writeln("    <fg=red>✗</> {$error}");
            }
            $output->writeln('');
        }

        $this->displayDiff($diff, $output);

        if ($isDryRun) {
            $output->writeln('  <fg=gray>Dry run — no changes written.</> ');
            $output->writeln('');

            return Command::SUCCESS;
        }

        $shouldWrite = $applyFlag || confirm(
            label: 'Apply these changes to your schema?',
            default: true,
        );

        if ($shouldWrite) {
            SchemaWriter::write($schemaPath, $patched);
            $output->writeln('');
            $output->writeln("  <fg=green>✓ Schema updated:</> {$schemaPath}");
        } else {
            $output->writeln('  <fg=gray>No changes written.</> ');
        }

        $output->writeln('');

        return Command::SUCCESS;
    }

    /**
     * Read and JSON-decode a schema file. Returns null on invalid JSON.
     *
     * @return array<string, mixed>|null
     */
    protected function readSchema(string $path): ?array
    {
        $contents = file_get_contents($path);
        $schema   = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $schema;
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

    /**
     * Output an error and return FAILURE exit code.
     */
    protected function fail(OutputInterface $output, string $message, bool $jsonOutput): int
    {
        if ($jsonOutput) {
            $output->writeln(json_encode([
                'success' => false,
                'error'   => $message,
            ], JSON_PRETTY_PRINT));
        } else {
            $output->writeln('');
            $output->writeln("  <fg=red>Error:</> {$message}");
            $output->writeln('');
        }

        return Command::FAILURE;
    }
}
