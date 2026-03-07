<?php

namespace CodingSunshine\Ensemble\Console;

use CodingSunshine\Ensemble\Config\ConfigStore;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

class ConfigCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('config')
            ->setDescription('View or modify Ensemble CLI configuration')
            ->addArgument('action', InputArgument::OPTIONAL, 'Action: list, set, get, clear', 'list')
            ->addArgument('key', InputArgument::OPTIONAL, 'Config key (dot notation, e.g. providers.anthropic.api_key)')
            ->addArgument('value', InputArgument::OPTIONAL, 'Value to set (for "set" action)')
            ->setHelp(<<<'HELP'
            View or modify your saved Ensemble CLI configuration (~/.ensemble/config.json).

            <info>Usage:</info>
              ensemble config                              List all saved config
              ensemble config list                         List all saved config
              ensemble config get default_provider         Get a single value
              ensemble config set default_provider openai  Set a value
              ensemble config clear providers.openai       Remove a key

            <info>Common keys:</info>
              default_provider                Provider name (anthropic, openai, openrouter, ollama)
              providers.anthropic.api_key     Anthropic API key
              providers.openai.api_key        OpenAI API key
              providers.openrouter.api_key    OpenRouter API key
            HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = new ConfigStore();
        $action = $input->getArgument('action');
        $key = $input->getArgument('key');

        return match ($action) {
            'list' => $this->listConfig($config, $output),
            'get' => $this->getConfig($config, $key, $output),
            'set' => $this->setConfig($config, $key, $input->getArgument('value'), $output),
            'clear' => $this->clearConfig($config, $key, $output),
            default => $this->unknownAction($action, $output),
        };
    }

    protected function listConfig(ConfigStore $config, OutputInterface $output): int
    {
        $data = $config->all();

        if (empty($data)) {
            $output->writeln('');
            $output->writeln('  <fg=gray>No configuration saved yet.</>');
            $output->writeln('  Run <options=bold>ensemble draft</> or <options=bold>ensemble new</> to get started.');
            $output->writeln('');

            return Command::SUCCESS;
        }

        $output->writeln('');
        $output->writeln('  <fg=cyan;options=bold>Ensemble Configuration</> — ~/.ensemble/config.json');
        $output->writeln('');

        $this->displayFlattened($data, $output);

        $output->writeln('');

        return Command::SUCCESS;
    }

    protected function getConfig(ConfigStore $config, ?string $key, OutputInterface $output): int
    {
        if (! $key) {
            warning('Please specify a key. Usage: ensemble config get <key>');

            return Command::FAILURE;
        }

        $value = $config->get($key);

        if ($value === null) {
            $output->writeln("  <fg=gray>Key \"{$key}\" is not set.</>");

            return Command::SUCCESS;
        }

        if (is_array($value)) {
            $output->writeln('');
            $this->displayFlattened([$key => $value], $output, prefix: $key);
            $output->writeln('');
        } else {
            $displayed = $this->maskIfSensitive($key, (string) $value);
            $output->writeln("  {$key} = <fg=green>{$displayed}</>");
        }

        return Command::SUCCESS;
    }

    protected function setConfig(ConfigStore $config, ?string $key, ?string $value, OutputInterface $output): int
    {
        if (! $key || $value === null) {
            warning('Usage: ensemble config set <key> <value>');

            return Command::FAILURE;
        }

        $config->set($key, $value);
        info("Set {$key}");

        return Command::SUCCESS;
    }

    protected function clearConfig(ConfigStore $config, ?string $key, OutputInterface $output): int
    {
        if (! $key) {
            warning('Please specify a key. Usage: ensemble config clear <key>');

            return Command::FAILURE;
        }

        $config->set($key, null);
        info("Cleared {$key}");

        return Command::SUCCESS;
    }

    protected function unknownAction(string $action, OutputInterface $output): int
    {
        warning("Unknown action \"{$action}\". Use: list, get, set, clear");

        return Command::FAILURE;
    }

    /**
     * Recursively display config as flattened dot-notation keys.
     */
    protected function displayFlattened(array $data, OutputInterface $output, string $prefix = ''): void
    {
        foreach ($data as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value)) {
                $this->displayFlattened($value, $output, $fullKey);
            } else {
                $displayed = $this->maskIfSensitive($fullKey, (string) ($value ?? '<null>'));
                $output->writeln("  <fg=yellow>{$fullKey}</> = <fg=green>{$displayed}</>");
            }
        }
    }

    /**
     * Mask API keys for display — show first 8 and last 4 characters.
     */
    protected function maskIfSensitive(string $key, string $value): string
    {
        if (! str_contains($key, 'api_key') && ! str_contains($key, 'secret')) {
            return $value;
        }

        if (strlen($value) <= 12) {
            return str_repeat('*', strlen($value));
        }

        return substr($value, 0, 8).'...'.substr($value, -4);
    }
}
