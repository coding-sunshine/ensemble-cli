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

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\spin;

/**
 * ensemble agent — autonomous AI fix loop.
 *
 * Sends a prompt, applies the patch, validates, and if errors remain
 * automatically feeds them back to the AI for another round — up to
 * --max-iterations times. Stops as soon as the schema is valid.
 *
 * Use this when you want Ensemble to self-correct without manual
 * back-and-forth. Great for large structural changes.
 *
 * Example:
 *   ensemble agent "Add a complete SaaS billing system with plans, subscriptions and invoices"
 *   ensemble agent "Fix all validation errors" --max-iterations=5
 */
class AgentCommand extends Command
{
    use Concerns\OutputsJson;
    use Concerns\ResolvesAIProvider;

    public const DEFAULT_MAX_ITERATIONS = 3;

    protected function configure(): void
    {
        $this
            ->setName('agent')
            ->setDescription('Autonomous AI agent: prompt → patch → validate → self-correct (loop until clean or max iterations reached)')
            ->addArgument('prompt', InputArgument::REQUIRED, 'Natural language goal for the agent to fulfil')
            ->addOption('schema', null, InputOption::VALUE_REQUIRED, 'Path to the ensemble.json file', './ensemble.json')
            ->addOption('max-iterations', null, InputOption::VALUE_REQUIRED, 'Max AI rounds before giving up', (string) self::DEFAULT_MAX_ITERATIONS)
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Skip confirmation and apply changes immediately')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show final diff only; do not write changes')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output machine-readable JSON')
            ->addOption('provider', null, InputOption::VALUE_REQUIRED, 'AI provider (anthropic, openai, openrouter, ollama, prism)')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key for the AI provider')
            ->addOption('model', null, InputOption::VALUE_REQUIRED, 'AI model to use');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $schemaPath    = $input->getOption('schema');
        $userPrompt    = $input->getArgument('prompt');
        $maxIterations = max(1, (int) $input->getOption('max-iterations'));
        $isDryRun      = (bool) $input->getOption('dry-run');
        $applyFlag     = (bool) $input->getOption('apply');
        $jsonOutput    = (bool) $input->getOption('json');

        if (! file_exists($schemaPath)) {
            return $this->fail($output, "Schema file not found: {$schemaPath}", $jsonOutput);
        }

        [$fullSchema, $readError] = $this->readSchema($schemaPath);
        if ($fullSchema === null) {
            return $this->fail($output, $readError ?? "Could not read schema: {$schemaPath}", $jsonOutput);
        }

        $provider   = $this->resolveProvider($input);
        $patcher    = new SchemaPatcher();
        $validator  = new SchemaValidator();
        $systemPrompt = $this->loadSystemPrompt();
        $patchDef   = SchemaJsonSchema::patchDefinition();

        // ── Agentic loop ──────────────────────────────────────────────────────
        $current    = $fullSchema;
        $patched    = $fullSchema;
        $errors     = [];
        $iteration  = 0;
        $history    = [];

        if (! $jsonOutput) {
            $output->writeln('');
            $output->writeln('  <fg=cyan;options=bold>Ensemble Agent</>');
            $output->writeln("  <fg=gray>Goal: {$userPrompt}</>");
            $output->writeln("  <fg=gray>Max iterations: {$maxIterations}</>");
            $output->writeln('');
        }

        while ($iteration < $maxIterations) {
            $iteration++;
            $round = "[{$iteration}/{$maxIterations}]";

            if (! $jsonOutput) {
                $output->writeln("  <fg=yellow>{$round}</> Building patch…");
            }

            // Build the prompt — on the first round use the original goal,
            // on subsequent rounds append validation errors from the previous attempt
            if ($iteration === 1) {
                $message = $patcher->buildDeltaPrompt($current, $userPrompt);
            } else {
                $errorList = implode("\n- ", $errors);
                $message = $patcher->buildDeltaPrompt($current, $userPrompt)
                    . "\n\nThe previous patch produced a schema with validation errors:\n- {$errorList}"
                    . "\n\nPlease return a corrected patch that fixes these issues.";
            }

            $patch = spin(
                message: "{$round} Requesting AI patch…",
                callback: fn () => $provider->completeStructured($systemPrompt, $message, $patchDef),
            );

            $patched = $patcher->applyPatch($current, $patch);

            $validator->validate($patched);
            $errors = $validator->errors();

            $diff = $patcher->diff($current, $patched);

            $history[] = [
                'iteration' => $iteration,
                'added'     => $diff['added'],
                'modified'  => $diff['modified'],
                'removed'   => $diff['removed'],
                'errors'    => $errors,
            ];

            if (! $jsonOutput) {
                $addCount = count($diff['added']);
                $modCount = count($diff['modified']);
                $remCount = count($diff['removed']);
                $summary  = "{$addCount} added, {$modCount} modified, {$remCount} removed";

                if (empty($errors)) {
                    $output->writeln("  <fg=green>✓ {$round}</> Schema valid. ({$summary})");
                } else {
                    $output->writeln("  <fg=yellow>~ {$round}</> {$summary} — " . count($errors) . ' error(s); retrying…');
                }
            }

            if (empty($errors)) {
                break;
            }

            // Use the patched version as the base for the next iteration
            $current = $patched;
        }

        // ── Output ────────────────────────────────────────────────────────────
        $success = empty($errors);

        if ($jsonOutput) {
            $finalDiff = $patcher->diff($fullSchema, $patched);
            $output->writeln(json_encode([
                'success'    => $success,
                'iterations' => $iteration,
                'added'      => $finalDiff['added'],
                'modified'   => $finalDiff['modified'],
                'removed'    => $finalDiff['removed'],
                'history'    => $history,
                'schema'     => $patched,
                'errors'     => $errors,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $success ? Command::SUCCESS : Command::FAILURE;
        }

        $output->writeln('');

        if (! $success) {
            $output->writeln("  <fg=red;options=bold>Agent could not produce a valid schema in {$maxIterations} iteration(s).</>");
            $output->writeln('  Remaining errors:');
            foreach ($errors as $err) {
                $output->writeln("    <fg=red>✗</> {$err}");
            }
            $output->writeln('  <fg=gray>Try --max-iterations=5 or refine your prompt.</>');
            $output->writeln('');
        }

        // Show cumulative diff (original → final)
        $finalDiff = $patcher->diff($fullSchema, $patched);
        $this->displayDiff($finalDiff, $output);

        if ($isDryRun) {
            $output->writeln('  <fg=gray>Dry run — no changes written.</>');
            $output->writeln('');

            return $success ? Command::SUCCESS : Command::FAILURE;
        }

        if (! $success && ! $applyFlag) {
            $shouldWrite = confirm(
                label: 'Schema still has errors. Apply the best attempt anyway?',
                default: false,
            );
        } elseif ($applyFlag) {
            $shouldWrite = true;
        } else {
            $shouldWrite = confirm(
                label: 'Apply these changes to your schema?',
                default: true,
            );
        }

        if ($shouldWrite) {
            SchemaWriter::write($schemaPath, $patched);
            $output->writeln("  <fg=green>✓ Schema updated:</> {$schemaPath}");
            $output->writeln("  <fg=gray>Completed in {$iteration} iteration(s).</>");
        } else {
            $output->writeln('  <fg=gray>No changes written.</>');
        }

        $output->writeln('');

        return $success ? Command::SUCCESS : Command::FAILURE;
    }

    /** @return array{0: array<string, mixed>|null, 1: string|null} */
    protected function readSchema(string $path): array
    {
        try {
            return [SchemaWriter::readLoose($path), null];
        } catch (\RuntimeException $e) {
            return [null, $e->getMessage()];
        }
    }

    protected function loadSystemPrompt(): string
    {
        $stub = __DIR__ . '/../../stubs/ai-patch-prompt.md';

        return file_exists($stub)
            ? file_get_contents($stub)
            : 'You are a Laravel Ensemble schema editor. Return only a partial JSON patch containing the sections to add or modify.';
    }

    /**
     * Display added/modified/removed sections in a readable format.
     *
     * @param  array{added: array<string, mixed>, modified: array<string, mixed>, removed: list<string>}  $diff
     */
    protected function displayDiff(array $diff, OutputInterface $output): void
    {
        if (! empty($diff['added'])) {
            $output->writeln('  <fg=green;options=bold>Added</>');
            foreach ($diff['added'] as $key => $value) {
                $output->writeln("    <fg=green>+</> {$key}");
            }
            $output->writeln('');
        }

        if (! empty($diff['modified'])) {
            $output->writeln('  <fg=yellow;options=bold>Modified</>');
            foreach ($diff['modified'] as $key => $value) {
                $output->writeln("    <fg=yellow>~</> {$key}");
            }
            $output->writeln('');
        }

        if (! empty($diff['removed'])) {
            $output->writeln('  <fg=red;options=bold>Removed</>');
            foreach ($diff['removed'] as $key) {
                $output->writeln("    <fg=red>-</> {$key}");
            }
            $output->writeln('');
        }

        if (empty($diff['added']) && empty($diff['modified']) && empty($diff['removed'])) {
            $output->writeln('  <fg=gray>No changes detected.</>');
            $output->writeln('');
        }
    }

    protected function fail(OutputInterface $output, string $message, bool $jsonOutput): int
    {
        if ($jsonOutput) {
            $output->writeln(json_encode(['success' => false, 'error' => $message], JSON_PRETTY_PRINT));
        } else {
            $output->writeln("<error>{$message}</error>");
        }

        return Command::FAILURE;
    }
}
