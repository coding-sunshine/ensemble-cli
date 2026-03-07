<?php

namespace CodingSunshine\Ensemble\Console;

use CodingSunshine\Ensemble\Config\ConfigStore;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class DoctorCommand extends Command
{
    protected const REQUIRED_EXTENSIONS = [
        'ctype', 'curl', 'filter', 'hash', 'json',
        'mbstring', 'openssl', 'session', 'tokenizer',
    ];

    protected const RECOMMENDED_EXTENSIONS = [
        'pdo_sqlite', 'pdo_mysql', 'pdo_pgsql',
    ];

    protected int $warnings = 0;

    protected int $errors = 0;

    protected function configure(): void
    {
        $this
            ->setName('doctor')
            ->setDescription('Check your environment for Ensemble CLI compatibility');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('');
        $output->writeln('  <fg=cyan;options=bold>Ensemble Doctor</> — Environment Health Check');
        $output->writeln('');

        $this->checkPhp($output);
        $this->checkExtensions($output);
        $this->checkComposer($output);
        $this->checkGit($output);
        $this->checkNode($output);
        $this->checkAiProviders($output);
        $this->checkConfig($output);

        $output->writeln('');

        if ($this->errors > 0) {
            $output->writeln("  <fg=red;options=bold>✗ {$this->errors} error(s)</> and <fg=yellow>{$this->warnings} warning(s)</> found.");
            $output->writeln('  Please fix the errors above before using Ensemble.');
        } elseif ($this->warnings > 0) {
            $output->writeln("  <fg=green;options=bold>✓ No errors.</> <fg=yellow>{$this->warnings} warning(s)</> — see above.");
        } else {
            $output->writeln('  <fg=green;options=bold>✓ Everything looks great!</> Your environment is ready.');
        }

        $output->writeln('');

        return $this->errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    protected function checkPhp(OutputInterface $output): void
    {
        $version = PHP_VERSION;
        $major = PHP_MAJOR_VERSION;
        $minor = PHP_MINOR_VERSION;

        $output->writeln('  <options=bold>PHP</>');

        if ($major > 8 || ($major === 8 && $minor >= 2)) {
            $this->pass($output, "PHP {$version}");
        } else {
            $this->fail($output, "PHP {$version} — requires PHP 8.2 or higher");
        }
    }

    protected function checkExtensions(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('  <options=bold>PHP Extensions</>');

        $loaded = get_loaded_extensions();

        foreach (self::REQUIRED_EXTENSIONS as $ext) {
            if (in_array($ext, $loaded)) {
                $this->pass($output, $ext);
            } else {
                $this->fail($output, "{$ext} — required but not installed");
            }
        }

        foreach (self::RECOMMENDED_EXTENSIONS as $ext) {
            if (in_array($ext, $loaded)) {
                $this->pass($output, $ext);
            } else {
                $this->warn($output, "{$ext} — optional, install for database support");
            }
        }
    }

    protected function checkComposer(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('  <options=bold>Composer</>');

        $finder = new ExecutableFinder();
        $composer = $finder->find('composer');

        if ($composer) {
            $process = new Process([$composer, '--version']);
            $process->run();
            $version = trim($process->getOutput());
            $this->pass($output, $version ?: 'Composer found');
        } else {
            $this->fail($output, 'Composer not found — install from https://getcomposer.org');
        }
    }

    protected function checkGit(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('  <options=bold>Git</>');

        $process = new Process(['git', '--version']);
        $process->run();

        if ($process->isSuccessful()) {
            $this->pass($output, trim($process->getOutput()));
        } else {
            $this->warn($output, 'Git not found — optional but recommended');
        }
    }

    protected function checkNode(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('  <options=bold>Node / Package Managers</>');

        $this->checkBinary($output, 'node', '--version', 'Node.js');
        $this->checkBinary($output, 'npm', '--version', 'npm', optional: true);
        $this->checkBinary($output, 'pnpm', '--version', 'pnpm', optional: true);
        $this->checkBinary($output, 'bun', '--version', 'Bun', optional: true);
        $this->checkBinary($output, 'yarn', '--version', 'Yarn', optional: true);
    }

    protected function checkAiProviders(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('  <options=bold>AI Provider Keys</>');

        $envKeys = [
            'ENSEMBLE_API_KEY' => 'Global Ensemble key',
            'ANTHROPIC_API_KEY' => 'Anthropic (Claude)',
            'OPENAI_API_KEY' => 'OpenAI (GPT)',
            'OPENROUTER_API_KEY' => 'OpenRouter',
        ];

        $anyFound = false;

        foreach ($envKeys as $env => $label) {
            $value = getenv($env);

            if ($value && $value !== '') {
                $this->pass($output, "{$label} — \${$env} is set");
                $anyFound = true;
            }
        }

        $process = new Process(['curl', '-s', '-o', '/dev/null', '-w', '%{http_code}', 'http://localhost:11434/api/tags']);
        $process->setTimeout(5);
        $process->run();

        if ($process->isSuccessful() && trim($process->getOutput()) === '200') {
            $this->pass($output, 'Ollama — running locally on port 11434');
            $anyFound = true;
        }

        if (! $anyFound) {
            $this->warn($output, 'No AI provider keys found. Set one via environment variable or run: ensemble config set providers.anthropic.api_key YOUR_KEY');
        }
    }

    protected function checkConfig(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('  <options=bold>Configuration</>');

        $config = new ConfigStore();
        $data = $config->all();

        if (! empty($data)) {
            $provider = $config->get('default_provider');
            $this->pass($output, 'Config file exists (~/.ensemble/config.json)');

            if ($provider) {
                $this->pass($output, "Default provider: {$provider}");
            }
        } else {
            $output->writeln('    <fg=gray>○</> No saved configuration yet (optional)');
        }
    }

    protected function checkBinary(OutputInterface $output, string $binary, string $versionFlag, string $label, bool $optional = false): void
    {
        $finder = new ExecutableFinder();
        $path = $finder->find($binary);

        if ($path) {
            $process = new Process([$path, $versionFlag]);
            $process->run();
            $version = trim($process->getOutput());
            $this->pass($output, "{$label} {$version}");
        } elseif ($optional) {
            $output->writeln("    <fg=gray>○</> {$label} — not installed (optional)");
        } else {
            $this->warn($output, "{$label} — not found");
        }
    }

    protected function pass(OutputInterface $output, string $message): void
    {
        $output->writeln("    <fg=green>✓</> {$message}");
    }

    protected function warn(OutputInterface $output, string $message): void
    {
        $this->warnings++;
        $output->writeln("    <fg=yellow>!</> {$message}");
    }

    protected function fail(OutputInterface $output, string $message): void
    {
        $this->errors++;
        $output->writeln("    <fg=red>✗</> {$message}");
    }
}
