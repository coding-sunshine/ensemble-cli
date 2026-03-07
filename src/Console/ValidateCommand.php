<?php

namespace CodingSunshine\Ensemble\Console;

use CodingSunshine\Ensemble\Schema\SchemaValidator;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ValidateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('validate')
            ->setDescription('Validate an ensemble.json schema for structural correctness')
            ->addArgument('path', InputArgument::OPTIONAL, 'Path to the ensemble.json file', './ensemble.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getArgument('path');

        if (! file_exists($path)) {
            $output->writeln('');
            $output->writeln("  <fg=red>File not found:</> {$path}");
            $output->writeln('');

            return Command::FAILURE;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, ['yaml', 'yml'])) {
            $output->writeln('');
            $output->writeln('  <fg=red>YAML is not supported.</> Please use ensemble.json instead.');
            $output->writeln('');

            return Command::FAILURE;
        }

        $contents = file_get_contents($path);
        $schema = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $output->writeln('');
            $output->writeln("  <fg=red>Invalid JSON:</> ".json_last_error_msg());
            $output->writeln('');

            return Command::FAILURE;
        }

        $validator = new SchemaValidator();
        $valid = $validator->validate($schema);
        $errors = $validator->errors();
        $warnings = $validator->warnings();

        $output->writeln('');
        $output->writeln("  <fg=cyan;options=bold>Ensemble Validate</> — {$path}");
        $output->writeln('');

        if (! empty($errors)) {
            $output->writeln('  <fg=red;options=bold>Errors</>');

            foreach ($errors as $error) {
                $output->writeln("    <fg=red>✗</> {$error}");
            }

            $output->writeln('');
        }

        if (! empty($warnings)) {
            $output->writeln('  <fg=yellow;options=bold>Warnings</>');

            foreach ($warnings as $warning) {
                $output->writeln("    <fg=yellow>!</> {$warning}");
            }

            $output->writeln('');
        }

        if ($valid && empty($warnings)) {
            $output->writeln('  <fg=green>✓ Schema is valid.</> No errors or warnings.');
            $output->writeln('');
        } elseif ($valid) {
            $output->writeln('  <fg=green>✓ Schema is valid</> with '.count($warnings).' warning(s).');
            $output->writeln('');
        } else {
            $output->writeln('  <fg=red>✗ Schema has '.count($errors).' error(s)</> and '.count($warnings).' warning(s).');
            $output->writeln('');
        }

        return $valid ? Command::SUCCESS : Command::FAILURE;
    }
}
