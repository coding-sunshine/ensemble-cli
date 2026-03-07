<?php

namespace CodingSunshine\Ensemble\Console;

use CodingSunshine\Ensemble\Schema\SchemaWriter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ShowCommand extends Command
{
    /**
     * Configure the command options.
     */
    protected function configure(): void
    {
        $this
            ->setName('show')
            ->setDescription('Display a summary of an ensemble.json schema')
            ->addArgument('path', InputArgument::OPTIONAL, 'Path to the ensemble.json file', './ensemble.json');
    }

    /**
     * Execute the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getArgument('path');
        $schema = SchemaWriter::read($path);

        $output->writeln('');
        $output->writeln("  <fg=cyan;options=bold>Ensemble Schema</> — {$path}");
        $output->writeln('');

        $this->displaySection($output, 'Application', $this->buildAppLines($schema));
        $this->displaySection($output, 'Models', $this->buildModelLines($schema));
        $this->displaySection($output, 'Controllers', $this->buildControllerLines($schema));
        $this->displaySection($output, 'Pages', $this->buildPageLines($schema));
        $this->displaySection($output, 'Recipes', $this->buildRecipeLines($schema));
        $this->displaySection($output, 'Notifications', $this->buildNotificationLines($schema));
        $this->displaySection($output, 'Workflows', $this->buildWorkflowLines($schema));

        $output->writeln('');

        return Command::SUCCESS;
    }

    /**
     * Display a labeled section with lines.
     *
     * @param  array<int, string>  $lines
     */
    protected function displaySection(OutputInterface $output, string $title, array $lines): void
    {
        if (empty($lines)) {
            return;
        }

        $output->writeln("  <options=bold>{$title}</>");

        foreach ($lines as $line) {
            $output->writeln("    {$line}");
        }

        $output->writeln('');
    }

    /**
     * @return array<int, string>
     */
    protected function buildAppLines(array $schema): array
    {
        $app = $schema['app'] ?? [];
        $lines = [];

        if (isset($app['name'])) {
            $lines[] = "Name:    <fg=green>{$app['name']}</>";
        }

        if (isset($app['stack'])) {
            $lines[] = "Stack:   <fg=green>{$app['stack']}</>";
        }

        if (isset($app['ui'])) {
            $lines[] = "UI:      <fg=green>{$app['ui']}</>";
        }

        if (isset($schema['version'])) {
            $lines[] = "Version: <fg=gray>{$schema['version']}</>";
        }

        return $lines;
    }

    /**
     * @return array<int, string>
     */
    protected function buildModelLines(array $schema): array
    {
        $models = $schema['models'] ?? [];

        if (empty($models)) {
            return ['<fg=gray>(none)</>'];
        }

        $lines = [];

        foreach ($models as $modelName => $definition) {
            $fieldCount = isset($definition['fields']) ? count($definition['fields']) : 0;
            $relationCount = isset($definition['relationships']) ? count($definition['relationships']) : 0;

            $details = [];

            if ($fieldCount > 0) {
                $details[] = "{$fieldCount} fields";
            }

            if ($relationCount > 0) {
                $details[] = "{$relationCount} relations";
            }

            if (! empty($definition['softDeletes'])) {
                $details[] = 'soft-deletes';
            }

            if (! empty($definition['policies'])) {
                $details[] = count($definition['policies']).' policies';
            }

            $suffix = $details ? ' <fg=gray>('.implode(', ', $details).')</>' : '';
            $lines[] = "<fg=yellow>{$modelName}</>{$suffix}";

            if (isset($definition['fields'])) {
                foreach ($definition['fields'] as $fieldName => $fieldType) {
                    $lines[] = "  <fg=gray>├</> {$fieldName}: <fg=blue>{$fieldType}</>";
                }
            }

            if (isset($definition['relationships'])) {
                $relEntries = array_keys($definition['relationships']);
                $lastKey = end($relEntries);

                foreach ($definition['relationships'] as $relName => $relType) {
                    $connector = ($relName === $lastKey) ? '└' : '├';
                    $lines[] = "  <fg=gray>{$connector}</> {$relName} → <fg=magenta>{$relType}</>";
                }
            }
        }

        return $lines;
    }

    /**
     * @return array<int, string>
     */
    protected function buildControllerLines(array $schema): array
    {
        $controllers = $schema['controllers'] ?? [];

        if (empty($controllers)) {
            return ['<fg=gray>(none)</>'];
        }

        $lines = [];

        foreach ($controllers as $name => $definition) {
            $resource = $definition['resource'] ?? 'web';
            $lines[] = "<fg=yellow>{$name}Controller</> <fg=gray>[{$resource}]</>";
        }

        return $lines;
    }

    /**
     * @return array<int, string>
     */
    protected function buildPageLines(array $schema): array
    {
        $pages = $schema['pages'] ?? [];

        if (empty($pages)) {
            return ['<fg=gray>(none)</>'];
        }

        $lines = [];

        foreach ($pages as $routeName => $definition) {
            $layout = $definition['layout'] ?? 'default';
            $lines[] = "<fg=yellow>{$routeName}</> <fg=gray>[{$layout}]</>";
        }

        return $lines;
    }

    /**
     * @return array<int, string>
     */
    protected function buildRecipeLines(array $schema): array
    {
        $recipes = $schema['recipes'] ?? [];

        if (empty($recipes)) {
            return ['<fg=gray>(none)</>'];
        }

        $lines = [];

        foreach ($recipes as $recipe) {
            $name = $recipe['name'] ?? 'unknown';
            $package = $recipe['package'] ?? null;
            $packageInfo = $package ? " <fg=gray>({$package})</>" : ' <fg=gray>(built-in)</>';
            $lines[] = "<fg=yellow>{$name}</>{$packageInfo}";
        }

        return $lines;
    }

    /**
     * @return array<int, string>
     */
    protected function buildNotificationLines(array $schema): array
    {
        $notifications = $schema['notifications'] ?? [];

        if (empty($notifications)) {
            return [];
        }

        $lines = [];

        foreach ($notifications as $name => $definition) {
            $channels = isset($definition['channels']) ? implode(', ', $definition['channels']) : 'mail';
            $to = $definition['to'] ?? '—';
            $lines[] = "<fg=yellow>{$name}</> <fg=gray>[{$channels}]</> → {$to}";

            if (isset($definition['subject'])) {
                $lines[] = "  <fg=gray>└</> \"{$definition['subject']}\"";
            }
        }

        return $lines;
    }

    /**
     * @return array<int, string>
     */
    protected function buildWorkflowLines(array $schema): array
    {
        $workflows = $schema['workflows'] ?? [];

        if (empty($workflows)) {
            return [];
        }

        $lines = [];

        foreach ($workflows as $name => $definition) {
            $model = $definition['model'] ?? '?';
            $field = $definition['field'] ?? '?';
            $lines[] = "<fg=yellow>{$name}</> <fg=gray>({$model}.{$field})</>";

            if (isset($definition['states'])) {
                $lines[] = '  States: <fg=green>'.implode(' → ', $definition['states']).'</>';
            }

            if (isset($definition['transitions'])) {
                foreach ($definition['transitions'] as $transName => $trans) {
                    $from = $trans['from'] ?? '?';
                    $to = $trans['to'] ?? '?';
                    $lines[] = "  <fg=gray>├</> {$transName}: <fg=blue>{$from}</> → <fg=blue>{$to}</>";
                }
            }
        }

        return $lines;
    }
}
