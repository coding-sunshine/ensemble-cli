<?php

namespace CodingSunshine\Ensemble\Console;

use CodingSunshine\Ensemble\AI\ConversationEngine;
use CodingSunshine\Ensemble\Console\Enums\NodePackageManager;
use CodingSunshine\Ensemble\Scaffold\StarterKitResolver;
use CodingSunshine\Ensemble\Schema\SchemaWriter;
use CodingSunshine\Ensemble\Schema\TemplateRegistry;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Support\ProcessUtils;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

use function Illuminate\Filesystem\join_paths;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class NewCommand extends Command
{
    use Concerns\ConfiguresPrompts;
    use Concerns\DisplaysDryRun;
    use Concerns\InteractsWithHerdOrValet;
    use Concerns\ResolvesAIProvider;
    use Concerns\TracksProgress;

    const DATABASE_DRIVERS = ['mysql', 'mariadb', 'pgsql', 'sqlite', 'sqlsrv'];

    /**
     * The Composer instance.
     *
     * @var \Illuminate\Support\Composer
     */
    protected $composer;

    /**
     * The generated schema, if any.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $schema = null;

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('new')
            ->setDescription('Create a new Laravel application with optional AI-powered scaffolding')
            ->addArgument('name', InputArgument::REQUIRED)
            ->addOption('dev', null, InputOption::VALUE_NONE, 'Install the latest "development" release')
            ->addOption('git', null, InputOption::VALUE_NONE, 'Initialize a Git repository')
            ->addOption('branch', null, InputOption::VALUE_REQUIRED, 'The branch that should be created for a new repository', $this->defaultBranch())
            ->addOption('github', null, InputOption::VALUE_OPTIONAL, 'Create a new repository on GitHub', false)
            ->addOption('organization', null, InputOption::VALUE_REQUIRED, 'The GitHub organization to create the new repository for')
            ->addOption('database', null, InputOption::VALUE_REQUIRED, 'The database driver your application will use. Possible values are: '.implode(', ', self::DATABASE_DRIVERS))
            ->addOption('react', null, InputOption::VALUE_NONE, 'Install the React Starter Kit')
            ->addOption('svelte', null, InputOption::VALUE_NONE, 'Install the Svelte Starter Kit')
            ->addOption('vue', null, InputOption::VALUE_NONE, 'Install the Vue Starter Kit')
            ->addOption('livewire', null, InputOption::VALUE_NONE, 'Install the Livewire Starter Kit')
            ->addOption('livewire-class-components', null, InputOption::VALUE_NONE, 'Generate stand-alone Livewire class components')
            ->addOption('workos', null, InputOption::VALUE_NONE, 'Use WorkOS for authentication')
            ->addOption('no-authentication', null, InputOption::VALUE_NONE, 'Do not generate authentication scaffolding')
            ->addOption('pest', null, InputOption::VALUE_NONE, 'Install the Pest testing framework')
            ->addOption('phpunit', null, InputOption::VALUE_NONE, 'Install the PHPUnit testing framework')
            ->addOption('npm', null, InputOption::VALUE_NONE, 'Install and build NPM dependencies')
            ->addOption('pnpm', null, InputOption::VALUE_NONE, 'Install and build NPM dependencies via PNPM')
            ->addOption('bun', null, InputOption::VALUE_NONE, 'Install and build NPM dependencies via Bun')
            ->addOption('yarn', null, InputOption::VALUE_NONE, 'Install and build NPM dependencies via Yarn')
            ->addOption('boost', null, InputOption::VALUE_NONE, 'Install Laravel Boost to improve AI assisted coding')
            ->addOption('no-boost', null, InputOption::VALUE_NONE, 'Skip Laravel Boost installation')
            ->addOption('using', null, InputOption::VALUE_OPTIONAL, 'Install a custom starter kit from a community maintained package')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Forces install even if the directory already exists')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Path to an existing ensemble.json schema file')
            ->addOption('template', 't', InputOption::VALUE_REQUIRED, 'Use a bundled template: '.implode(', ', TemplateRegistry::names()))
            ->addOption('no-ai', null, InputOption::VALUE_NONE, 'Skip AI-powered scaffolding, create a plain Laravel project')
            ->addOption('provider', null, InputOption::VALUE_REQUIRED, 'AI provider: anthropic, openai, openrouter, ollama')
            ->addOption('model', null, InputOption::VALUE_REQUIRED, 'Override the default AI model for the chosen provider')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key for the AI provider')
            ->addOption('ai-budget', null, InputOption::VALUE_REQUIRED, 'AI budget level for ensemble:build (none, low, medium, high)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would happen without creating anything');
    }

    /**
     * Interact with the user before validating the input.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        parent::interact($input, $output);

        $this->configurePrompts($input, $output);

        $this->displayHeader($output);

        $this->ensureExtensionsAreAvailable($input, $output);

        if (! $input->getArgument('name')) {
            $input->setArgument('name', text(
                label: 'What is the name of your project?',
                placeholder: 'E.g. example-app',
                required: 'The project name is required.',
                validate: function ($value) use ($input) {
                    if (preg_match('/[^\pL\pN\-_.]/', $value) !== 0) {
                        return 'The name may only contain letters, numbers, dashes, underscores, and periods.';
                    }

                    if ($input->getOption('force') !== true) {
                        try {
                            $this->verifyApplicationDoesntExist($this->getInstallationDirectory($value));
                        } catch (RuntimeException $e) {
                            return 'Application already exists.';
                        }
                    }
                },
            ));
        }

        if ($input->getOption('force') !== true) {
            $this->verifyApplicationDoesntExist(
                $this->getInstallationDirectory($input->getArgument('name'))
            );
        }

        $this->resolveSchema($input, $output);

        if ($this->schema && $this->applySchemaStarterKit($input)) {
            // Schema determined the starter kit; skip interactive prompts for kit selection
        } elseif (! $this->usingStarterKit($input)) {
            match (select(
                label: 'Which starter kit would you like to install?',
                options: [
                    'none' => 'None',
                    'react' => 'React',
                    'svelte' => 'Svelte',
                    'vue' => 'Vue',
                    'livewire' => 'Livewire',
                ],
                default: 'none',
            )) {
                'react' => $input->setOption('react', true),
                'svelte' => $input->setOption('svelte', true),
                'vue' => $input->setOption('vue', true),
                'livewire' => $input->setOption('livewire', true),
                default => null,
            };

            if ($this->usingLaravelStarterKit($input)) {
                match (select(
                    label: 'Which authentication provider do you prefer?',
                    options: [
                        'laravel' => "Laravel's built-in authentication",
                        'workos' => 'WorkOS (Requires WorkOS account)',
                        'none' => 'No authentication scaffolding',
                    ],
                    default: 'laravel',
                )) {
                    'laravel' => $input->setOption('workos', false),
                    'workos' => $input->setOption('workos', true),
                    'none' => $input->setOption('no-authentication', true),
                    default => null,
                };
            }

            if ($input->getOption('livewire') &&
                ! $input->getOption('workos') &&
                ! $input->getOption('no-authentication')) {
                $input->setOption('livewire-class-components', ! confirm(
                    label: 'Would you like to use single-file Livewire components?',
                    default: true,
                ));
            }
        }

        if (! $input->getOption('phpunit') && ! $input->getOption('pest')) {
            $input->setOption('pest', select(
                label: 'Which testing framework do you prefer?',
                options: ['Pest', 'PHPUnit'],
                default: 'Pest',
            ) === 'Pest');
        }

        if (! $input->getOption('boost') && ! $input->getOption('no-boost')) {
            $input->setOption('boost', confirm(
                label: 'Do you want to install Laravel Boost to improve AI assisted coding?',
            ));
        }
    }

    /**
     * Resolve the schema from --from file or AI conversation.
     */
    protected function resolveSchema(InputInterface $input, ?OutputInterface $output = null): void
    {
        if ($fromPath = $input->getOption('from')) {
            $this->schema = SchemaWriter::read($fromPath);
            info("Loaded schema from {$fromPath}");

            return;
        }

        if ($templateName = $input->getOption('template')) {
            $this->schema = TemplateRegistry::load($templateName);
            info("Loaded \"{$templateName}\" template.");

            return;
        }

        if ($input->getOption('no-ai')) {
            return;
        }

        if (! $input->isInteractive()) {
            return;
        }

        $wantsAi = confirm(
            label: 'Would you like AI to help design your application?',
            default: true,
        );

        if (! $wantsAi) {
            return;
        }

        $provider = $this->resolveProvider($input);
        $engine = new ConversationEngine($provider, $output);
        $result = $engine->run();

        if ($result === null) {
            return;
        }

        $this->schema = $result;
    }

    /**
     * Ensure the schema is loaded when interact() was skipped (non-interactive mode).
     */
    protected function ensureSchemaLoaded(InputInterface $input): void
    {
        if ($this->schema !== null) {
            return;
        }

        if ($fromPath = $input->getOption('from')) {
            $this->schema = SchemaWriter::read($fromPath);

            return;
        }

        if ($templateName = $input->getOption('template')) {
            $this->schema = TemplateRegistry::load($templateName);
        }
    }

    /**
     * When running non-interactively with a schema, auto-derive sensible defaults
     * so that `ensemble new my-app --from=schema.json -n` works fully headless.
     */
    protected function applyHeadlessDefaults(InputInterface $input): void
    {
        if ($input->isInteractive() || ! $this->schema) {
            return;
        }

        if (! $this->usingStarterKit($input)) {
            $this->applySchemaStarterKit($input);
        }

        if (! $input->getOption('phpunit') && ! $input->getOption('pest')) {
            $input->setOption('pest', true);
        }

        if (! $input->getOption('database')) {
            $input->setOption('database', 'sqlite');
        }
    }

    /**
     * Calculate how many major steps the build will perform for the progress indicator.
     */
    protected function calculateTotalSteps(InputInterface $input): int
    {
        $steps = 1; // Creating Laravel project

        $steps++; // Configuring environment (always)

        if ($input->getOption('git') || $input->getOption('github') !== false) {
            $steps++; // Git init
        }

        if ($input->getOption('pest')) {
            $steps++; // Pest
        }

        if ($input->getOption('boost') && ! $input->getOption('no-boost')) {
            $steps++; // Boost
        }

        $steps++; // Node dependencies (always counted, may be skipped)

        $steps++; // Install Ensemble package (always)

        if ($this->schema) {
            $steps++; // Ensemble schema scaffolding
        }

        return $steps;
    }

    /**
     * Apply starter kit selection from the schema. Returns true if successfully applied.
     */
    protected function applySchemaStarterKit(InputInterface $input): bool
    {
        $stack = $this->schema['app']['stack'] ?? null;

        if (! $stack || ! StarterKitResolver::isValid($stack)) {
            return false;
        }

        match ($stack) {
            'react' => $input->setOption('react', true),
            'svelte' => $input->setOption('svelte', true),
            'vue' => $input->setOption('vue', true),
            'livewire' => $input->setOption('livewire', true),
            default => null,
        };

        return $this->usingStarterKit($input);
    }

    /**
     * Display the Ensemble header with gradient colors.
     *
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    protected function displayHeader(OutputInterface $output): void
    {
        $output->writeln('');

        $lines = [
            ' ███████╗ ███╗   ██╗ ███████╗ ███████╗ ███╗   ███╗ ██████╗  ██╗      ███████╗',
            ' ██╔════╝ ████╗  ██║ ██╔════╝ ██╔════╝ ████╗ ████║ ██╔══██╗ ██║      ██╔════╝',
            ' █████╗   ██╔██╗ ██║ ███████╗ █████╗   ██╔████╔██║ ██████╔╝ ██║      █████╗  ',
            ' ██╔══╝   ██║╚██╗██║ ╚════██║ ██╔══╝   ██║╚██╔╝██║ ██╔══██╗ ██║      ██╔══╝  ',
            ' ███████╗ ██║ ╚████║ ███████║ ███████╗ ██║ ╚═╝ ██║ ██████╔╝ ███████╗ ███████╗',
            ' ╚══════╝ ╚═╝  ╚═══╝ ╚══════╝ ╚══════╝ ╚═╝     ╚═╝ ╚═════╝  ╚══════╝ ╚══════╝',
        ];

        $gradients = [
            'Ember' => [227, 221, 215, 209, 203, 197],
            'Ocean' => [81, 75, 69, 63, 57, 21],
            'Aurora' => [51, 50, 49, 48, 47, 41],
            'Cyberpunk' => [201, 165, 129, 93, 57, 21],
            'Sunset' => [214, 208, 202, 196, 160, 124],
            'Vaporwave' => [213, 177, 141, 105, 69, 39],
        ];

        $themeName = array_rand($gradients);
        $gradient = $gradients[$themeName];

        foreach ($lines as $index => $line) {
            $color = $gradient[$index];
            $output->writeln("\e[38;5;{$color}m{$line}\e[0m");
        }
    }

    /**
     * Ensure that the required PHP extensions are installed.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     *
     * @throws \RuntimeException
     */
    protected function ensureExtensionsAreAvailable(InputInterface $input, OutputInterface $output): void
    {
        $availableExtensions = get_loaded_extensions();

        $missingExtensions = collect([
            'ctype',
            'filter',
            'hash',
            'mbstring',
            'openssl',
            'session',
            'tokenizer',
        ])->reject(fn ($extension) => in_array($extension, $availableExtensions));

        if ($missingExtensions->isEmpty()) {
            return;
        }

        throw new \RuntimeException(
            sprintf('The following PHP extensions are required but are not installed: %s', $missingExtensions->join(', ', ', and '))
        );
    }

    /**
     * Execute the command.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->validateDatabaseOption($input);

        $this->ensureSchemaLoaded($input);
        $this->applyHeadlessDefaults($input);

        if ($input->getOption('dry-run') && $this->schema) {
            $this->displayDryRun($output, $this->schema, 'new');

            return Command::SUCCESS;
        }

        $name = rtrim($input->getArgument('name'), '/\\');

        $directory = $this->getInstallationDirectory($name);

        $this->composer = new Composer(new Filesystem(), $directory);

        $version = $this->getVersion($input);

        if (! $input->getOption('force')) {
            $this->verifyApplicationDoesntExist($directory);
        }

        if ($input->getOption('force') && $directory === '.') {
            throw new RuntimeException('Cannot use --force option when using current directory for installation!');
        }

        $this->initializeProgress($this->calculateTotalSteps($input));

        $composer = $this->findComposer();
        $phpBinary = $this->phpBinary();

        $createProjectCommand = $composer." create-project laravel/laravel \"$directory\" $version --remove-vcs --prefer-dist --no-scripts";

        $starterKit = $this->getStarterKit($input);

        if ($starterKit) {
            $createProjectCommand = $composer." create-project {$starterKit} \"{$directory}\" --stability=dev";

            if ($this->usingLaravelStarterKit($input) && $input->getOption('livewire-class-components')) {
                $createProjectCommand = str_replace(" {$starterKit} ", " {$starterKit}:dev-components ", $createProjectCommand);
            }

            if ($this->usingLaravelStarterKit($input) && $input->getOption('workos')) {
                $createProjectCommand = str_replace(" {$starterKit} ", " {$starterKit}:dev-workos ", $createProjectCommand);
            }

            if (! $this->usingLaravelStarterKit($input) && str_contains($starterKit, '://')) {
                $createProjectCommand = 'npx tiged@latest '.$starterKit.' "'.$directory.'" && cd "'.$directory.'" && composer install';
            }
        }

        $commands = [
            $createProjectCommand,
            $composer." run post-root-package-install -d \"$directory\"",
            $phpBinary." \"$directory/artisan\" key:generate --ansi",
        ];

        if ($directory != '.' && $input->getOption('force')) {
            if (PHP_OS_FAMILY == 'Windows') {
                array_unshift($commands, "(if exist \"$directory\" rd /s /q \"$directory\")");
            } else {
                array_unshift($commands, "rm -rf \"$directory\"");
            }
        }

        if (PHP_OS_FAMILY != 'Windows') {
            $commands[] = "chmod 755 \"$directory/artisan\"";
        }

        $this->step($output, 'Creating Laravel project...');

        if (($process = $this->runCommands($commands, $input, $output))->isSuccessful()) {
            if ($name !== '.') {
                $this->step($output, 'Configuring environment...');

                $this->replaceInFile(
                    'APP_URL=http://localhost',
                    'APP_URL='.$this->generateAppUrl($name, $directory),
                    $directory.'/.env'
                );

                [$database, $migrate] = $this->promptForDatabaseOptions($directory, $input);

                $this->configureDefaultDatabaseConnection($directory, $database, $name);

                if ($migrate) {
                    if ($database === 'sqlite') {
                        touch($directory.'/database/database.sqlite');
                    }

                    $commands = [
                        trim(sprintf(
                            $this->phpBinary().' artisan migrate %s',
                            ! $input->isInteractive() ? '--no-interaction' : '',
                        )),
                    ];

                    $this->runCommands($commands, $input, $output, workingPath: $directory);
                }
            }

            if ($input->getOption('git') || $input->getOption('github') !== false) {
                $this->step($output, 'Initializing Git repository...');
                $this->createRepository($directory, $input, $output);
            }

            if ($input->getOption('pest')) {
                $this->step($output, 'Installing Pest testing framework...');
                $this->installPest($directory, $input, $output);
            }

            if ($input->getOption('boost') && ! $input->getOption('no-boost')) {
                $this->step($output, 'Installing Laravel Boost...');
                $this->installBoost($directory, $input, $output);
            }

            if ($input->getOption('github') !== false) {
                $this->pushToGitHub($name, $directory, $input, $output);
                $output->writeln('');
            }

            [$packageManager, $runPackageManager] = $this->determinePackageManager($directory, $input);

            $this->configureComposerScripts($packageManager);

            if ($input->getOption('pest')) {
                $output->writeln('');
            }

            if (! $runPackageManager && $input->isInteractive()) {
                $runPackageManager = confirm(
                    label: 'Would you like to run <options=bold>'.$packageManager->installCommand().'</> and <options=bold>'.$packageManager->buildCommand().'</>?'
                );
            }

            foreach (NodePackageManager::allLockFiles() as $lockFile) {
                if (! in_array($lockFile, $packageManager->lockFiles()) && file_exists($directory.'/'.$lockFile)) {
                    (new Filesystem())->delete($directory.'/'.$lockFile);
                }
            }

            if ($runPackageManager) {
                $this->step($output, 'Installing Node dependencies...');
                $this->runCommands([$packageManager->installCommand(), $packageManager->buildCommand()], $input, $output, workingPath: $directory);
            }

            if ($input->getOption('boost') && ! $input->getOption('no-boost')) {
                $this->configureBoostComposerScript();
                $this->commitChanges('Configure Boost post-update script', $directory, $input, $output);
            }

            $this->step($output, 'Installing Ensemble...');
            $this->installEnsemblePackage($directory, $input, $output);

            if ($this->schema) {
                $this->step($output, 'Scaffolding with Ensemble...');
                $this->applyEnsembleSchema($directory, $input, $output);
            }

            $output->writeln("  <bg=blue;fg=white> INFO </> Application ready in <options=bold>[{$name}]</>. You can start your local development using:".PHP_EOL);
            $output->writeln('<fg=gray>➜</> <options=bold>cd '.$name.'</>');

            if (! $runPackageManager) {
                $output->writeln('<fg=gray>➜</> <options=bold>'.$packageManager->installCommand().' && '.$packageManager->buildCommand().'</>');
            }

            if ($this->isParkedOnHerdOrValet($directory)) {
                $url = $this->generateAppUrl($name, $directory);
                $output->writeln('<fg=gray>➜</> Open: <options=bold;href='.$url.'>'.$url.'</>');
            } else {
                $output->writeln('<fg=gray>➜</> <options=bold>composer run dev</>');
            }

            $output->writeln('');
            $output->writeln('  New to Laravel? Check out our <href=https://laravel.com/docs/installation#next-steps>documentation</>. <options=bold>Build something amazing!</>');
            $output->writeln('');
        }

        return $process->getExitCode();
    }

    /**
     * Install the coding-sunshine/ensemble companion package into the project.
     */
    protected function installEnsemblePackage(string $directory, InputInterface $input, OutputInterface $output): void
    {
        try {
            $composerBinary = $this->findComposer();
            $this->runCommands(
                [$composerBinary.' require coding-sunshine/ensemble --dev'],
                $input,
                $output,
                workingPath: $directory,
            );
        } catch (Throwable) {
            warning('Could not install coding-sunshine/ensemble. You can install it later with:');
            $output->writeln('  <options=bold>composer require coding-sunshine/ensemble --dev</>');
            $output->writeln('');
        }
    }

    /**
     * Write the schema, install recipe packages, and run ensemble:build + migrations.
     */
    protected function applyEnsembleSchema(string $directory, InputInterface $input, OutputInterface $output): void
    {
        $schemaPath = $directory.'/ensemble.json';
        SchemaWriter::write($schemaPath, $this->schema);
        info('Wrote ensemble.json to project root.');

        @mkdir($directory.'/.ensemble', 0755, true);

        $this->installRecipePackages($directory, $input, $output);

        try {
            $budgetFlag = $input->getOption('ai-budget')
                ? ' --budget='.$input->getOption('ai-budget')
                : '';

            $this->runCommands([
                $this->phpBinary().' artisan ensemble:build --no-interaction'.$budgetFlag,
            ], $input, $output, workingPath: $directory);

            $this->runCommands([
                $this->phpBinary().' artisan migrate --force',
            ], $input, $output, workingPath: $directory);

            $this->runCommands([
                $this->phpBinary().' artisan db:seed --force',
            ], $input, $output, workingPath: $directory);

            $this->commitChanges('Scaffold application with Ensemble', $directory, $input, $output);
        } catch (Throwable) {
            warning('Could not run ensemble:build. You can run it manually with:');
            $output->writeln('  <options=bold>php artisan ensemble:build</>');
            $output->writeln('');
        }
    }

    /**
     * Install Composer packages referenced by the schema recipes.
     */
    protected function installRecipePackages(string $directory, InputInterface $input, OutputInterface $output): void
    {
        if (! isset($this->schema['recipes']) || empty($this->schema['recipes'])) {
            return;
        }

        $packages = array_filter(array_column($this->schema['recipes'], 'package'));

        if (empty($packages)) {
            return;
        }

        $composerBinary = $this->findComposer();
        $packageList = implode(' ', $packages);

        $this->runCommands(
            [$composerBinary.' require '.$packageList],
            $input,
            $output,
            workingPath: $directory,
        );
    }

    /**
     * Determine the Node package manager to use.
     *
     * @param  string  $directory
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @return array{NodePackageManager, bool}
     */
    protected function determinePackageManager(string $directory, InputInterface $input): array
    {
        if ($input->getOption('pnpm')) {
            return [NodePackageManager::PNPM, true];
        }

        if ($input->getOption('bun')) {
            return [NodePackageManager::BUN, true];
        }

        if ($input->getOption('yarn')) {
            return [NodePackageManager::YARN, true];
        }

        if ($input->getOption('npm')) {
            return [NodePackageManager::NPM, true];
        }

        foreach (NodePackageManager::cases() as $packageManager) {
            if ($packageManager === NodePackageManager::NPM) {
                continue;
            }

            foreach ($packageManager->lockFiles() as $lockFile) {
                if (file_exists($directory.'/'.$lockFile)) {
                    return [$packageManager, false];
                }
            }
        }

        return [NodePackageManager::NPM, false];
    }

    /**
     * Return the local machine's default Git branch if set or default to `main`.
     *
     * @return string
     */
    protected function defaultBranch()
    {
        $process = new Process(['git', 'config', '--global', 'init.defaultBranch']);

        $process->run();

        $output = trim($process->getOutput());

        return $process->isSuccessful() && $output ? $output : 'main';
    }

    /**
     * Configure the default database connection.
     *
     * @param  string  $directory
     * @param  string  $database
     * @param  string  $name
     * @return void
     */
    protected function configureDefaultDatabaseConnection(string $directory, string $database, string $name)
    {
        $this->pregReplaceInFile(
            '/DB_CONNECTION=.*/',
            'DB_CONNECTION='.$database,
            $directory.'/.env'
        );

        $this->pregReplaceInFile(
            '/DB_CONNECTION=.*/',
            'DB_CONNECTION='.$database,
            $directory.'/.env.example'
        );

        if ($database === 'sqlite') {
            $environment = file_get_contents($directory.'/.env');

            if (! str_contains($environment, '# DB_HOST=127.0.0.1')) {
                $this->commentDatabaseConfigurationForSqlite($directory);

                return;
            }

            return;
        }

        $this->uncommentDatabaseConfiguration($directory);

        $defaultPorts = [
            'pgsql' => '5432',
            'sqlsrv' => '1433',
        ];

        if (isset($defaultPorts[$database])) {
            $this->replaceInFile(
                'DB_PORT=3306',
                'DB_PORT='.$defaultPorts[$database],
                $directory.'/.env'
            );

            $this->replaceInFile(
                'DB_PORT=3306',
                'DB_PORT='.$defaultPorts[$database],
                $directory.'/.env.example'
            );
        }

        $this->replaceInFile(
            'DB_DATABASE=laravel',
            'DB_DATABASE='.str_replace('-', '_', strtolower($name)),
            $directory.'/.env'
        );

        $this->replaceInFile(
            'DB_DATABASE=laravel',
            'DB_DATABASE='.str_replace('-', '_', strtolower($name)),
            $directory.'/.env.example'
        );
    }

    /**
     * Determine if the application is using Laravel 11 or newer.
     *
     * @param  string  $directory
     * @return bool
     */
    public function usingLaravelVersionOrNewer(int $usingVersion, string $directory): bool
    {
        $version = json_decode(file_get_contents($directory.'/composer.json'), true)['require']['laravel/framework'];
        $version = str_replace('^', '', $version);
        $version = explode('.', $version)[0];

        return $version >= $usingVersion;
    }

    /**
     * Comment the irrelevant database configuration entries for SQLite applications.
     *
     * @param  string  $directory
     * @return void
     */
    protected function commentDatabaseConfigurationForSqlite(string $directory): void
    {
        $defaults = [
            'DB_HOST=127.0.0.1',
            'DB_PORT=3306',
            'DB_DATABASE=laravel',
            'DB_USERNAME=root',
            'DB_PASSWORD=',
        ];

        $this->replaceInFile(
            $defaults,
            collect($defaults)->map(fn ($default) => "# {$default}")->all(),
            $directory.'/.env'
        );

        $this->replaceInFile(
            $defaults,
            collect($defaults)->map(fn ($default) => "# {$default}")->all(),
            $directory.'/.env.example'
        );
    }

    /**
     * Uncomment the relevant database configuration entries for non SQLite applications.
     *
     * @param  string  $directory
     * @return void
     */
    protected function uncommentDatabaseConfiguration(string $directory)
    {
        $defaults = [
            '# DB_HOST=127.0.0.1',
            '# DB_PORT=3306',
            '# DB_DATABASE=laravel',
            '# DB_USERNAME=root',
            '# DB_PASSWORD=',
        ];

        $this->replaceInFile(
            $defaults,
            collect($defaults)->map(fn ($default) => substr($default, 2))->all(),
            $directory.'/.env'
        );

        $this->replaceInFile(
            $defaults,
            collect($defaults)->map(fn ($default) => substr($default, 2))->all(),
            $directory.'/.env.example'
        );
    }

    /**
     * Determine the default database connection.
     *
     * @param  string  $directory
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @return array
     */
    protected function promptForDatabaseOptions(string $directory, InputInterface $input)
    {
        $defaultDatabase = collect(
            $databaseOptions = $this->databaseOptions()
        )->keys()->first();

        if (! $input->getOption('database') && $this->usingStarterKit($input)) {
            $migrate = false;

            $input->setOption('database', 'sqlite');
        }

        if (! $input->getOption('database') && $input->isInteractive()) {
            $input->setOption('database', select(
                label: 'Which database will your application use?',
                options: $databaseOptions,
                default: $defaultDatabase,
            ));

            if ($input->getOption('database') !== 'sqlite') {
                $migrate = confirm(
                    label: 'Default database updated. Would you like to run the default database migrations?'
                );
            } else {
                $migrate = true;
            }
        }

        return [$input->getOption('database') ?? $defaultDatabase, $migrate ?? $input->hasOption('database')];
    }

    /**
     * Get the available database options.
     *
     * @return array
     */
    protected function databaseOptions(): array
    {
        return collect([
            'sqlite' => ['SQLite', extension_loaded('pdo_sqlite')],
            'mysql' => ['MySQL', extension_loaded('pdo_mysql')],
            'mariadb' => ['MariaDB', extension_loaded('pdo_mysql')],
            'pgsql' => ['PostgreSQL', extension_loaded('pdo_pgsql')],
            'sqlsrv' => ['SQL Server', extension_loaded('pdo_sqlsrv')],
        ])
            ->sortBy(fn ($database) => $database[1] ? 0 : 1)
            ->map(fn ($database) => $database[0].($database[1] ? '' : ' (Missing PDO extension)'))
            ->all();
    }

    /**
     * Validate the database driver input.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     */
    protected function validateDatabaseOption(InputInterface $input)
    {
        if ($input->getOption('database') && ! in_array($input->getOption('database'), self::DATABASE_DRIVERS)) {
            throw new \InvalidArgumentException("Invalid database driver [{$input->getOption('database')}]. Possible values are: ".implode(', ', self::DATABASE_DRIVERS).'.');
        }
    }

    /**
     * Install Pest into the application.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    protected function installPest(string $directory, InputInterface $input, OutputInterface $output)
    {
        $composerBinary = $this->findComposer();

        $commands = [
            $composerBinary.' remove phpunit/phpunit --dev --no-update',
            $composerBinary.' require pestphp/pest pestphp/pest-plugin-laravel --no-update --dev',
            $composerBinary.' update',
            $this->phpBinary().' ./vendor/bin/pest --init',
        ];

        $commands[] = $composerBinary.' require pestphp/pest-plugin-drift --dev';
        $commands[] = $this->phpBinary().' ./vendor/bin/pest --drift';
        $commands[] = $composerBinary.' remove pestphp/pest-plugin-drift --dev';

        $this->runCommands($commands, $input, $output, workingPath: $directory, env: [
            'PEST_NO_SUPPORT' => 'true',
        ]);

        if ($this->usingStarterKit($input)) {
            $this->replaceInFile(
                './vendor/bin/phpunit',
                './vendor/bin/pest',
                $directory.'/.github/workflows/tests.yml',
            );

            $contents = file_get_contents("$directory/tests/Pest.php");

            $contents = str_replace(
                " // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)",
                "    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)",
                $contents,
            );

            file_put_contents("$directory/tests/Pest.php", $contents);

            $directoryIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$directory/tests"));

            foreach ($directoryIterator as $testFile) {
                if ($testFile->isDir()) {
                    continue;
                }

                $contents = file_get_contents($testFile);

                file_put_contents(
                    $testFile,
                    str_replace("\n\nuses(\Illuminate\Foundation\Testing\RefreshDatabase::class);", '', $contents),
                );
            }
        }

        $this->commitChanges('Install Pest', $directory, $input, $output);
    }

    /**
     * Install Laravel Boost into the application.
     *
     * @param  string  $directory
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    protected function installBoost(string $directory, InputInterface $input, OutputInterface $output): void
    {
        $composerBinary = $this->findComposer();

        $commands = [
            $composerBinary.' require laravel/boost ^2.0 --dev -W',
            trim(sprintf(
                $this->phpBinary().' artisan boost:install %s',
                ! $input->isInteractive() ? '--no-interaction' : '',
            )),
        ];

        $this->runCommands($commands, $input, $output, workingPath: $directory);

        $this->commitChanges('Install Laravel Boost', $directory, $input, $output);
    }

    /**
     * Create a Git repository and commit the base Laravel skeleton.
     *
     * @param  string  $directory
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    protected function createRepository(string $directory, InputInterface $input, OutputInterface $output)
    {
        $branch = $input->getOption('branch') ?: $this->defaultBranch();

        $commands = [
            'git init -q',
            'git add .',
            'git commit -q -m "Set up a fresh Laravel app"',
            "git branch -M {$branch}",
        ];

        $this->runCommands($commands, $input, $output, workingPath: $directory);
    }

    /**
     * Commit any changes in the current working directory.
     *
     * @param  string  $message
     * @param  string  $directory
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    protected function commitChanges(string $message, string $directory, InputInterface $input, OutputInterface $output)
    {
        if (! $input->getOption('git') && $input->getOption('github') === false) {
            return;
        }

        $commands = [
            'git add .',
            "git commit -q -m \"$message\"",
        ];

        $this->runCommands($commands, $input, $output, workingPath: $directory);
    }

    /**
     * Create a GitHub repository and push the git log to it.
     *
     * @param  string  $name
     * @param  string  $directory
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    protected function pushToGitHub(string $name, string $directory, InputInterface $input, OutputInterface $output)
    {
        $process = new Process(['gh', 'auth', 'status']);
        $process->run();

        if (! $process->isSuccessful()) {
            $output->writeln('  <bg=yellow;fg=black> WARN </> Make sure the "gh" CLI tool is installed and that you\'re authenticated to GitHub. Skipping...'.PHP_EOL);

            return;
        }

        $name = $input->getOption('organization') ? $input->getOption('organization')."/$name" : $name;
        $flags = $input->getOption('github') ?: '--private';

        $commands = [
            "gh repo create {$name} --source=. --push {$flags}",
        ];

        $this->runCommands($commands, $input, $output, workingPath: $directory, env: ['GIT_TERMINAL_PROMPT' => 0]);
    }

    /**
     * Configure the Composer scripts for the selected package manager.
     *
     * @param  NodePackageManager  $packageManager
     * @return void
     */
    protected function configureComposerScripts(NodePackageManager $packageManager): void
    {
        $this->composer->modify(function (array $content) use ($packageManager) {
            if (windows_os()) {
                $content['scripts']['dev'] = [
                    'Composer\\Config::disableProcessTimeout',
                    "npx concurrently -c \"#93c5fd,#c4b5fd,#fdba74\" \"php artisan serve\" \"php artisan queue:listen --tries=1\" \"npm run dev\" --names='server,queue,vite'",
                ];
            }

            foreach (['dev', 'dev:ssr', 'setup'] as $scriptKey) {
                if (array_key_exists($scriptKey, $content['scripts'])) {
                    $content['scripts'][$scriptKey] = str_replace(
                        ['npm', 'npx', 'ppnpm'],
                        [$packageManager->value, $packageManager->runLocalOrRemoteCommand(), 'pnpm'],
                        $content['scripts'][$scriptKey],
                    );
                }
            }

            return $content;
        });
    }

    /**
     * Add boost:update command to the post-update-cmd Composer script.
     *
     * @return void
     */
    protected function configureBoostComposerScript(): void
    {
        $this->composer->modify(function (array $content) {
            $content['scripts']['post-update-cmd'][] = '@php artisan boost:update --ansi';

            return $content;
        });
    }

    /**
     * Verify that the application does not already exist.
     *
     * @param  string  $directory
     * @return void
     */
    protected function verifyApplicationDoesntExist($directory)
    {
        if ((is_dir($directory) || is_file($directory)) && $directory != getcwd()) {
            throw new RuntimeException('Application already exists!');
        }
    }

    /**
     * Generate a valid APP_URL for the given application name.
     *
     * @param  string  $name
     * @param  string  $directory
     * @return string
     */
    protected function generateAppUrl($name, $directory)
    {
        if (! $this->isParkedOnHerdOrValet($directory)) {
            return 'http://localhost:8000';
        }

        $hostname = mb_strtolower($name).'.'.$this->getTld();

        return $this->canResolveHostname($hostname) ? 'http://'.$hostname : 'http://localhost';
    }

    /**
     * Get the starter kit repository, if any.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @return string|null
     */
    protected function getStarterKit(InputInterface $input): ?string
    {
        if ($input->getOption('no-authentication')) {
            return match (true) {
                $input->getOption('react') => 'laravel/blank-react-starter-kit',
                $input->getOption('svelte') => 'laravel/blank-svelte-starter-kit',
                $input->getOption('vue') => 'laravel/blank-vue-starter-kit',
                $input->getOption('livewire') => 'laravel/blank-livewire-starter-kit',
                default => $input->getOption('using'),
            };
        }

        return match (true) {
            $input->getOption('react') => 'laravel/react-starter-kit',
            $input->getOption('svelte') => 'laravel/svelte-starter-kit',
            $input->getOption('vue') => 'laravel/vue-starter-kit',
            $input->getOption('livewire') => 'laravel/livewire-starter-kit',
            default => $input->getOption('using'),
        };
    }

    /**
     * Determine if a Laravel first-party starter kit has been chosen.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @return bool
     */
    protected function usingLaravelStarterKit(InputInterface $input): bool
    {
        return $this->usingStarterKit($input) &&
               str_starts_with($this->getStarterKit($input), 'laravel/');
    }

    /**
     * Determine if a starter kit is being used.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @return bool
     */
    protected function usingStarterKit(InputInterface $input)
    {
        return $input->getOption('react') || $input->getOption('svelte') || $input->getOption('vue') || $input->getOption('livewire') || $input->getOption('using');
    }

    /**
     * Get the TLD for the application.
     *
     * @return string
     */
    protected function getTld()
    {
        return $this->runOnValetOrHerd('tld') ?: 'test';
    }

    /**
     * Determine whether the given hostname is resolvable.
     *
     * @param  string  $hostname
     * @return bool
     */
    protected function canResolveHostname($hostname)
    {
        return gethostbyname($hostname.'.') !== $hostname.'.';
    }

    /**
     * Get the installation directory.
     *
     * @param  string  $name
     * @return string
     */
    protected function getInstallationDirectory(string $name)
    {
        if ($name === '.') {
            return '.';
        }

        return str_starts_with($name, DIRECTORY_SEPARATOR) ? $name : getcwd().'/'.$name;
    }

    /**
     * Get the version that should be downloaded.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @return string
     */
    protected function getVersion(InputInterface $input)
    {
        if ($input->getOption('dev')) {
            return 'dev-master';
        }

        return '';
    }

    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    protected function findComposer()
    {
        return implode(' ', $this->composer->findComposer());
    }

    /**
     * Get the path to the appropriate PHP binary.
     *
     * @return string
     */
    protected function phpBinary()
    {
        $phpBinary = function_exists('Illuminate\Support\php_binary')
            ? \Illuminate\Support\php_binary()
            : (new PhpExecutableFinder)->find(false);

        return $phpBinary !== false
            ? ProcessUtils::escapeArgument($phpBinary)
            : 'php';
    }

    /**
     * Run the given commands.
     *
     * @param  array  $commands
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @param  string|null  $workingPath
     * @param  array  $env
     * @return \Symfony\Component\Process\Process
     */
    protected function runCommands($commands, InputInterface $input, OutputInterface $output, ?string $workingPath = null, array $env = [])
    {
        if (! $output->isDecorated()) {
            $commands = array_map(function ($value) {
                if (Str::startsWith($value, ['chmod', 'rm', 'git', $this->phpBinary().' ./vendor/bin/pest'])) {
                    return $value;
                }

                return $value.' --no-ansi';
            }, $commands);
        }

        if ($input->getOption('quiet')) {
            $commands = array_map(function ($value) {
                if (Str::startsWith($value, ['chmod', 'rm', 'git', $this->phpBinary().' ./vendor/bin/pest'])) {
                    return $value;
                }

                return $value.' --quiet';
            }, $commands);
        }

        $process = Process::fromShellCommandline(implode(' && ', $commands), $workingPath, $env, null, null);

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

    /**
     * Replace the given file.
     *
     * @param  string  $replace
     * @param  string  $file
     * @return void
     */
    protected function replaceFile(string $replace, string $file)
    {
        $stubs = dirname(__DIR__).'/stubs';

        file_put_contents(
            $file,
            file_get_contents("$stubs/$replace"),
        );
    }

    /**
     * Replace the given string in the given file.
     *
     * @param  string|array  $search
     * @param  string|array  $replace
     * @param  string  $file
     * @return void
     */
    protected function replaceInFile(string|array $search, string|array $replace, string $file)
    {
        file_put_contents(
            $file,
            str_replace($search, $replace, file_get_contents($file))
        );
    }

    /**
     * Replace the given string in the given file using regular expressions.
     *
     * @param  string|array  $search
     * @param  string|array  $replace
     * @param  string  $file
     * @return void
     */
    protected function pregReplaceInFile(string $pattern, string $replace, string $file)
    {
        file_put_contents(
            $file,
            preg_replace($pattern, $replace, file_get_contents($file))
        );
    }

    /**
     * Delete the given file.
     *
     * @param  string  $file
     * @return void
     */
    protected function deleteFile(string $file)
    {
        unlink($file);
    }
}
