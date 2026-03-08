<?php

namespace CodingSunshine\Ensemble\Console;

use CodingSunshine\Ensemble\Http\LaraPluginsClient;
use CodingSunshine\Ensemble\Recipes\KnownRecipes;
use CodingSunshine\Ensemble\Schema\SchemaWriter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RecipeCommand extends Command
{
    use Concerns\OutputsJson;

    private ?LaraPluginsClient $laraPluginsClient;

    public function __construct(?LaraPluginsClient $laraPluginsClient = null)
    {
        parent::__construct();
        $this->laraPluginsClient = $laraPluginsClient;
    }

    protected function configure(): void
    {
        $this
            ->setName('recipe')
            ->setDescription('Manage recipes (Laravel packages) in your schema')
            ->addArgument('subcommand', InputArgument::REQUIRED, 'Subcommand: list, add, remove, search, info')
            ->addArgument('name', InputArgument::OPTIONAL, 'Recipe name/package or search query')
            ->addOption('schema', null, InputOption::VALUE_REQUIRED, 'Path to the ensemble.json file', './ensemble.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $subcommand = $input->getArgument('subcommand');
        $name = $input->getArgument('name');
        $schemaPath = $input->getOption('schema');

        return match ($subcommand) {
            'list'   => $this->runList($schemaPath, $output),
            'add'    => $this->runAdd($name, $schemaPath, $output),
            'remove' => $this->runRemove($name, $schemaPath, $output),
            'search' => $this->runSearch($name, $output),
            'info'   => $this->runInfo($name, $output),
            default  => $this->unknownSubcommand($subcommand, $output),
        };
    }

    // -------------------------------------------------------------------------
    // Subcommand handlers
    // -------------------------------------------------------------------------

    private function runList(string $schemaPath, OutputInterface $output): int
    {
        $output->writeln('');
        $output->writeln('  <fg=cyan;options=bold>Ensemble Recipes</>');
        $output->writeln('');

        // Built-in known recipes
        $output->writeln('  <options=bold>Built-in Recipes</>');
        foreach (KnownRecipes::all() as $recipe) {
            if ($recipe['package'] === null) {
                continue;
            }
            $output->writeln("    <fg=green>•</> <options=bold>{$recipe['name']}</> ({$recipe['package']})");
            $output->writeln("      <fg=gray>{$recipe['description']}</>");
        }
        $output->writeln('');

        // Packages already in schema
        if (file_exists($schemaPath)) {
            $schema = $this->readSchema($schemaPath);
            $schemaRecipes = $schema['recipes'] ?? [];

            if (! empty($schemaRecipes)) {
                $output->writeln('  <options=bold>In Your Schema</>');
                foreach ($schemaRecipes as $package) {
                    $known = KnownRecipes::findByPackage($package);
                    $label = $known ? " ({$known['name']})" : '';
                    $output->writeln("    <fg=yellow>•</> {$package}{$label}");
                }
                $output->writeln('');
            }
        }

        return Command::SUCCESS;
    }

    private function runAdd(string|null $name, string $schemaPath, OutputInterface $output): int
    {
        if ($name === null) {
            $output->writeln('  <fg=red>Error:</> Please provide a recipe name or package. Usage: recipe add <name>');

            return Command::FAILURE;
        }

        // Resolve name to package
        $package = $this->resolvePackage($name);

        if ($package === null) {
            $output->writeln("  <fg=red>Error:</> Unknown recipe '{$name}'. Use 'recipe search' to find packages.");

            return Command::FAILURE;
        }

        if (! file_exists($schemaPath)) {
            $output->writeln("  <fg=red>Error:</> Schema file not found: {$schemaPath}");

            return Command::FAILURE;
        }

        $schema = $this->readSchema($schemaPath);
        $recipes = $schema['recipes'] ?? [];

        if (in_array($package, $recipes, true)) {
            $output->writeln("  <fg=yellow>Already added:</> {$package}");

            return Command::SUCCESS;
        }

        $recipes[] = $package;
        $schema['recipes'] = $recipes;

        SchemaWriter::write($schemaPath, $schema);

        $output->writeln('');
        $output->writeln("  <fg=green>✓ Added:</> {$package}");
        $output->writeln('');

        return Command::SUCCESS;
    }

    private function runRemove(string|null $name, string $schemaPath, OutputInterface $output): int
    {
        if ($name === null) {
            $output->writeln('  <fg=red>Error:</> Please provide a recipe name or package. Usage: recipe remove <name>');

            return Command::FAILURE;
        }

        $package = $this->resolvePackage($name) ?? $name;

        if (! file_exists($schemaPath)) {
            $output->writeln("  <fg=red>Error:</> Schema file not found: {$schemaPath}");

            return Command::FAILURE;
        }

        $schema = $this->readSchema($schemaPath);
        $recipes = $schema['recipes'] ?? [];

        if (! in_array($package, $recipes, true)) {
            $output->writeln("  <fg=yellow>Not found in schema:</> {$package}");

            return Command::SUCCESS;
        }

        $schema['recipes'] = array_values(array_filter($recipes, fn ($r) => $r !== $package));

        SchemaWriter::write($schemaPath, $schema);

        $output->writeln('');
        $output->writeln("  <fg=green>✓ Removed:</> {$package}");
        $output->writeln('');

        return Command::SUCCESS;
    }

    private function runSearch(string|null $query, OutputInterface $output): int
    {
        if ($query === null) {
            $output->writeln('  <fg=red>Error:</> Please provide a search query. Usage: recipe search <query>');

            return Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln("  <fg=cyan;options=bold>Searching laraplugins.io for \"{$query}\"</>");
        $output->writeln('');

        $client = $this->getLaraPluginsClient();
        $results = $client->search($query);

        if (empty($results)) {
            $output->writeln('  <fg=gray>No results found.</>');
            $output->writeln('');

            return Command::SUCCESS;
        }

        foreach ($results as $result) {
            $package = $result['package_name'] ?? $result['name'] ?? '—';
            $description = $result['description'] ?? '';
            $health = $result['health_score'] ?? $result['health'] ?? null;
            $badge = $health !== null ? $client->formatHealthScore((string) $health) : '';

            $output->writeln("  <options=bold>{$package}</> {$badge}");

            if ($description) {
                $output->writeln("    <fg=gray>{$description}</>");
            }
        }

        $output->writeln('');

        return Command::SUCCESS;
    }

    private function runInfo(string|null $package, OutputInterface $output): int
    {
        if ($package === null) {
            $output->writeln('  <fg=red>Error:</> Please provide a package name. Usage: recipe info <package>');

            return Command::FAILURE;
        }

        // First check built-in registry
        $known = KnownRecipes::findByPackage($package) ?? KnownRecipes::findByFeatureKey($package);

        $output->writeln('');
        $output->writeln("  <fg=cyan;options=bold>{$package}</>");
        $output->writeln('');

        if ($known) {
            $output->writeln("  <options=bold>Name:</>        {$known['name']}");
            $output->writeln("  <options=bold>Package:</>     {$known['package']}");
            $output->writeln("  <options=bold>Description:</> {$known['description']}");
            $output->writeln('  <options=bold>Tags:</>        '.implode(', ', $known['tags']));
        }

        // Also fetch live details from laraplugins.io
        $client = $this->getLaraPluginsClient();
        $details = $client->getDetails($package);

        if ($details) {
            $health = $details['health_score'] ?? $details['health'] ?? null;

            if ($health !== null) {
                $badge = $client->formatHealthScore((string) $health);
                $output->writeln("  <options=bold>Health:</>      {$badge}");
            }

            if (isset($details['downloads'])) {
                $output->writeln("  <options=bold>Downloads:</>   {$details['downloads']}");
            }

            if (isset($details['stars'])) {
                $output->writeln("  <options=bold>Stars:</>       {$details['stars']}");
            }
        }

        if (! $known && ! $details) {
            $output->writeln('  <fg=gray>No information found for this package.</>');
        }

        $output->writeln('');

        return Command::SUCCESS;
    }

    private function unknownSubcommand(string $subcommand, OutputInterface $output): int
    {
        $output->writeln("  <fg=red>Error:</> Unknown subcommand '{$subcommand}'. Available: list, add, remove, search, info");

        return Command::FAILURE;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve a recipe name or feature key to its Composer package name.
     * Returns the input unchanged if it looks like a full package name (contains /).
     */
    private function resolvePackage(string $name): ?string
    {
        // Already a full composer package (vendor/name)
        if (str_contains($name, '/')) {
            return $name;
        }

        // Try by feature key first
        $recipe = KnownRecipes::findByFeatureKey($name);

        if ($recipe !== null) {
            return $recipe['package'];
        }

        // Try by recipe name
        $all = KnownRecipes::all();

        foreach ($all as $recipe) {
            if ($recipe['name'] === $name) {
                return $recipe['package'];
            }
        }

        return null;
    }

    /**
     * Read and JSON-decode a schema file.
     *
     * @return array<string, mixed>
     */
    private function readSchema(string $path): array
    {
        $contents = file_get_contents($path);
        $schema = json_decode($contents, true);

        return is_array($schema) ? $schema : [];
    }

    private function getLaraPluginsClient(): LaraPluginsClient
    {
        return $this->laraPluginsClient ?? new LaraPluginsClient();
    }
}
