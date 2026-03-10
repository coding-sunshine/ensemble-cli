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
        $this->checkLocalAiTools($output);
        $this->checkAiProviders($output);
        $this->checkConfig($output);
        $this->checkSchemaSync($output);
        $this->checkHerdValetSail($output);
        $this->checkNpmForStudio($output);

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
        $this->printNextSteps($output);

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

    protected function checkLocalAiTools(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('  <options=bold>Local AI Tools Detected</>');

        $tools = [];

        $finder = new ExecutableFinder();
        if ($finder->find('claude')) {
            $p = new Process(['claude', '--version']);
            $p->setTimeout(3);
            $p->run();
            if ($p->isSuccessful()) {
                $tools[] = ['claude-cli', 'Claude Code CLI', '--provider=claude-cli'];
            }
        }

        if ($finder->find('gemini')) {
            $p = new Process(['gemini', '--version']);
            $p->setTimeout(3);
            $p->run();
            if ($p->isSuccessful()) {
                $tools[] = ['gemini-cli', 'Gemini CLI', '--provider=gemini-cli'];
            }
        }

        $p = new Process(['ollama', 'list']);
        $p->setTimeout(3);
        $p->run();
        if ($p->isSuccessful()) {
            $tools[] = ['ollama', 'Ollama', '--provider=ollama'];
        }

        $p = new Process(['curl', '-s', '-o', '/dev/null', '-w', '%{http_code}', 'http://localhost:1234/v1/models']);
        $p->setTimeout(2);
        $p->run();
        if ($p->isSuccessful() && trim($p->getOutput()) === '200') {
            $tools[] = ['lmstudio', 'LM Studio', '--provider=lmstudio'];
        }

        if ($tools === []) {
            $output->writeln('    <fg=gray>○</> No local AI tools found (optional)');
        } else {
            foreach ($tools as [$provider, $label, $flag]) {
                $this->pass($output, "{$label} — use {$flag}");
            }
        }
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

        $config = new ConfigStore();
        $localProvider = $config->detectLocalProvider();

        if ($localProvider !== null) {
            $this->pass($output, "Local provider available: {$localProvider}");
            $anyFound = true;
        }

        if (! $anyFound) {
            $this->warn($output, 'No AI provider found.');
            $output->writeln('    <fg=cyan>→ Next step:</> Set an API key:');
            $output->writeln('          <comment>ANTHROPIC_API_KEY=sk-ant-... ensemble draft</comment>');
            $output->writeln('      Or install a free local tool:');
            $output->writeln('          <comment>npm install -g @anthropic-ai/claude-code</comment>  (claude-cli)');
            $output->writeln('          <comment>npm install -g @google/gemini-cli</comment>           (gemini-cli)');
            $output->writeln('      Then: <comment>ensemble config set default_provider claude-cli</comment>');
        } elseif ($localProvider !== null && ! $config->get('default_provider')) {
            $output->writeln('    <fg=cyan>ℹ TIP:</> Use local AI with no API key:');
            $output->writeln('      <comment>ensemble config set default_provider ' . $localProvider . '</comment>');
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

    protected function checkSchemaSync(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('  <options=bold>Schema Sync</>');

        $schemaPath = getcwd().'/ensemble.json';

        if (! file_exists($schemaPath)) {
            $output->writeln('    <fg=gray>○</> No ensemble.json found in current directory (optional)');

            return;
        }

        $contents = file_get_contents($schemaPath);
        $decoded = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->fail($output, 'ensemble.json is not valid JSON: '.json_last_error_msg());
        } else {
            $this->pass($output, 'ensemble.json found and valid');
        }
    }

    protected function checkHerdValetSail(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('  <options=bold>Local Development Environment</>');

        $finder = new ExecutableFinder();

        $herd = $finder->find('herd');
        $valet = $finder->find('valet');

        if ($herd) {
            $this->pass($output, 'Herd found');
        } elseif ($valet) {
            $this->pass($output, 'Valet found');
        } else {
            $output->writeln('    <fg=gray>○</> Herd/Valet not found (optional — also supports Laravel Sail)');
        }

        $sailPath = getcwd().'/vendor/bin/sail';

        if (file_exists($sailPath)) {
            $this->pass($output, 'Laravel Sail found');
        } else {
            $output->writeln('    <fg=gray>○</> Laravel Sail not found (optional)');
        }
    }

    protected function checkNpmForStudio(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('  <options=bold>Studio Requirements</>');

        $finder = new ExecutableFinder();

        $npm = $finder->find('npm');
        $bun = $finder->find('bun');
        $pnpm = $finder->find('pnpm');

        if ($npm || $bun || $pnpm) {
            $manager = $npm ? 'npm' : ($bun ? 'bun' : 'pnpm');
            $this->pass($output, "Package manager found ({$manager}) — required for Ensemble Studio");
        } else {
            $this->warn($output, 'No Node.js package manager found (npm/bun/pnpm) — required to build Ensemble Studio assets');
        }
    }

    protected function printNextSteps(OutputInterface $output): void
    {
        $output->writeln('  <options=bold>Quick start</>');
        $output->writeln('');
        $output->writeln('  If everything is ready, create a new project:');
        $output->writeln('    <comment>ensemble new my-app</comment>');
        $output->writeln('');
        $output->writeln('  Generate a schema without a project:');
        $output->writeln('    <comment>ensemble draft --output=ensemble.json</comment>');
        $output->writeln('');
        $output->writeln('  Add Ensemble to an existing Laravel app:');
        $output->writeln('    <comment>cd your-laravel-app && ensemble init</comment>');
        $output->writeln('    <comment>php artisan ensemble:install</comment>');
        $output->writeln('');
        $output->writeln('  Watch schema for changes:');
        $output->writeln('    <comment>ensemble watch --auto-build</comment>');
        $output->writeln('');
        $output->writeln('  Start the MCP server for AI agents:');
        $output->writeln('    <comment>ensemble mcp</comment>');
        $output->writeln('');
    }
}
