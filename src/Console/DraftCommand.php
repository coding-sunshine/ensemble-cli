<?php

namespace CodingSunshine\Ensemble\Console;

use CodingSunshine\Ensemble\AI\ConversationEngine;
use CodingSunshine\Ensemble\Schema\SchemaWriter;
use CodingSunshine\Ensemble\Schema\TemplateRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\info;
use function Laravel\Prompts\select;

class DraftCommand extends Command
{
    use Concerns\ConfiguresPrompts;
    use Concerns\ResolvesAIProvider;

    /**
     * Configure the command options.
     */
    protected function configure(): void
    {
        $this
            ->setName('draft')
            ->setDescription('Generate an ensemble.json schema using AI without creating a project')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output path for the schema file', './ensemble.json')
            ->addOption('template', 't', InputOption::VALUE_REQUIRED, 'Use a bundled template instead of AI: '.implode(', ', TemplateRegistry::names()))
            ->addOption('extend', 'e', InputOption::VALUE_REQUIRED, 'Extend an existing ensemble.json with AI additions')
            ->addOption('provider', null, InputOption::VALUE_REQUIRED, 'AI provider: anthropic, openai, openrouter, ollama')
            ->addOption('model', null, InputOption::VALUE_REQUIRED, 'Override the default AI model for the chosen provider')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key for the AI provider');
    }

    /**
     * Interact with the user before validating the input.
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        parent::interact($input, $output);

        $this->configurePrompts($input, $output);

        $this->displayDraftHeader($output);
    }

    /**
     * Execute the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $schema = $this->resolveSchemaFromTemplateOrAI($input, $output);

        if ($schema === null) {
            return Command::SUCCESS;
        }

        $outputPath = $input->getOption('output');
        SchemaWriter::write($outputPath, $schema);

        info("Schema written to {$outputPath}");

        $output->writeln('');
        $output->writeln('  To create a project from this schema, run:');
        $output->writeln('');
        $output->writeln("  <options=bold>ensemble new my-app --from={$outputPath}</>");
        $output->writeln('');

        return Command::SUCCESS;
    }

    /**
     * Resolve the schema from a bundled template or via the AI conversation.
     *
     * @return array<string, mixed>|null
     */
    protected function resolveSchemaFromTemplateOrAI(InputInterface $input, OutputInterface $output): ?array
    {
        $templateName = $input->getOption('template');

        if ($templateName) {
            if (! TemplateRegistry::exists($templateName)) {
                $output->writeln('');
                $output->writeln("  <fg=red>Unknown template \"{$templateName}\".</>");
                $output->writeln('  Available templates: <options=bold>'.implode(', ', TemplateRegistry::names()).'</>');
                $output->writeln('');

                return null;
            }

            $schema = TemplateRegistry::load($templateName);
            info("Loaded \"{$templateName}\" template.");

            return $schema;
        }

        if ($input->isInteractive() && ! $input->getOption('provider') && ! getenv('ENSEMBLE_API_KEY')) {
            $useTemplate = select(
                label: 'How would you like to create your schema?',
                options: [
                    'ai' => 'Design with AI (requires API key)',
                    'template' => 'Start from a bundled template (no AI needed)',
                ],
                default: 'ai',
            );

            if ($useTemplate === 'template') {
                $selected = select(
                    label: 'Choose a template',
                    options: TemplateRegistry::options(),
                );

                $schema = TemplateRegistry::load($selected);
                info("Loaded \"{$selected}\" template.");

                return $schema;
            }
        }

        $existingSchema = null;

        if ($extendPath = $input->getOption('extend')) {
            $existingSchema = SchemaWriter::read($extendPath);
            info("Extending schema from {$extendPath}");
        }

        $provider = $this->resolveProvider($input);
        $engine = new ConversationEngine($provider, $output);

        return $engine->run($existingSchema);
    }

    /**
     * Display a compact header for the draft command.
     */
    protected function displayDraftHeader(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('  <fg=cyan;options=bold>Laravel Ensemble</> — Draft Mode');
        $output->writeln('  <fg=gray>Generate an application schema with AI</>');
        $output->writeln('');
    }
}
