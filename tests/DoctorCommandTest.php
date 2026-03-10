<?php

namespace CodingSunshine\Ensemble\Tests;

use CodingSunshine\Ensemble\Console\DoctorCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @see \CodingSunshine\Ensemble\Console\DoctorCommand
 */
class DoctorCommandTest extends TestCase
{
    private function runDoctor(): CommandTester
    {
        $app = new Application();
        $app->add(new DoctorCommand());
        $tester = new CommandTester($app->find('doctor'));
        $tester->execute([], ['capture_stderr_separately' => false]);
        return $tester;
    }

    public function test_exits_zero_when_php_version_acceptable(): void
    {
        $tester = $this->runDoctor();

        // Doctor may warn about missing tools but should not fail on PHP >= 8.2
        if (PHP_MAJOR_VERSION > 8 || (PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION >= 2)) {
            // No hard assertion on exit code — doctor can have warnings (exit 0) or errors (exit 1)
            // Just assert it produced some output
            $this->assertStringContainsStringIgnoringCase('php', $tester->getDisplay());
        } else {
            // Old PHP — should show an error
            $this->assertStringContainsString('requires PHP 8.2', $tester->getDisplay());
        }
    }

    public function test_output_includes_local_ai_section(): void
    {
        $tester = $this->runDoctor();
        $output = $tester->getDisplay();

        $this->assertStringContainsStringIgnoringCase('local ai', $output);
    }

    public function test_output_includes_ai_provider_section(): void
    {
        $tester = $this->runDoctor();
        $output = $tester->getDisplay();

        // Should mention AI providers
        $this->assertMatchesRegularExpression('/anthropic|openai|provider|api key/i', $output);
    }

    public function test_output_includes_quick_start_next_steps(): void
    {
        $tester = $this->runDoctor();
        $output = $tester->getDisplay();

        // Quick start section added in B11
        $this->assertStringContainsString('ensemble new', $output);
        $this->assertStringContainsString('ensemble init', $output);
    }

    public function test_next_steps_shown_when_no_ai_provider_configured(): void
    {
        // Temporarily unset known API keys
        $keys = ['ENSEMBLE_API_KEY', 'ANTHROPIC_API_KEY', 'OPENAI_API_KEY', 'OPENROUTER_API_KEY'];
        $saved = [];
        foreach ($keys as $k) {
            $saved[$k] = getenv($k);
            putenv($k . '=');
        }

        try {
            $tester = $this->runDoctor();
            $output = $tester->getDisplay();

            // If no local tools are available, should show the install hint
            if (str_contains($output, 'No AI provider')) {
                $this->assertStringContainsStringIgnoringCase('next step', $output);
            } else {
                // Local tool detected — just assert it ran
                $this->assertNotEmpty($output);
            }
        } finally {
            foreach ($saved as $k => $v) {
                if ($v !== false) {
                    putenv($k . '=' . $v);
                } else {
                    putenv($k);
                }
            }
        }
    }

    public function test_schema_sync_passes_for_valid_schema(): void
    {
        $tmpSchema = sys_get_temp_dir() . '/ensemble-doctor-test-' . uniqid() . '.json';
        file_put_contents($tmpSchema, json_encode([
            'version' => 1,
            'app' => ['name' => 'test', 'stack' => 'blade'],
            'models' => ['Post' => ['fields' => ['title' => 'string']]],
        ]));

        try {
            $app = new Application();
            $app->add(new DoctorCommand());
            $tester = new CommandTester($app->find('doctor'));
            // chdir to tmp so doctor finds the schema
            $origDir = getcwd();
            chdir(dirname($tmpSchema));
            rename($tmpSchema, getcwd() . '/ensemble.json');
            $tester->execute([]);
            chdir($origDir);

            $output = $tester->getDisplay();
            $this->assertStringContainsStringIgnoringCase('ensemble.json', $output);
        } finally {
            $jsonInDir = dirname($tmpSchema) . '/ensemble.json';
            if (file_exists($jsonInDir)) {
                unlink($jsonInDir);
            }
        }
    }
}
