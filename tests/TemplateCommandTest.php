<?php

namespace CodingSunshine\Ensemble\Tests;

use CodingSunshine\Ensemble\Console\TemplateCommand;
use CodingSunshine\Ensemble\Schema\TemplateRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class TemplateCommandTest extends TestCase
{
    private string $tmpOutput;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpOutput = sys_get_temp_dir().'/ensemble-template-test-'.uniqid().'.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpOutput)) {
            unlink($this->tmpOutput);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeCommandTester(): CommandTester
    {
        $app = new Application();
        $app->add(new TemplateCommand());

        return new CommandTester($app->find('template'));
    }

    // -------------------------------------------------------------------------
    // list
    // -------------------------------------------------------------------------

    public function test_list_shows_all_built_in_templates(): void
    {
        $tester = $this->makeCommandTester();
        $tester->execute(['subcommand' => 'list']);

        $output = $tester->getDisplay();

        $this->assertStringContainsString('saas', $output);
        $this->assertStringContainsString('blog', $output);
        $this->assertStringContainsString('ecommerce', $output);
        $this->assertStringContainsString('marketplace', $output);
        $this->assertStringContainsString('booking', $output);
        $this->assertStringContainsString('inventory', $output);
        $this->assertStringContainsString('helpdesk', $output);
        $this->assertStringContainsString('lms', $output);
        $this->assertStringContainsString('social', $output);
        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_list_includes_usage_hint(): void
    {
        $tester = $this->makeCommandTester();
        $tester->execute(['subcommand' => 'list']);

        $output = $tester->getDisplay();

        $this->assertStringContainsString('install', $output);
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    public function test_show_displays_template_info(): void
    {
        $tester = $this->makeCommandTester();
        $tester->execute(['subcommand' => 'show', 'name' => 'saas']);

        $output = $tester->getDisplay();

        $this->assertStringContainsString('saas', $output);
        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_show_displays_model_list(): void
    {
        $tester = $this->makeCommandTester();
        $tester->execute(['subcommand' => 'show', 'name' => 'blog']);

        $output = $tester->getDisplay();

        $this->assertStringContainsString('Models', $output);
        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_show_fails_for_unknown_template(): void
    {
        $tester = $this->makeCommandTester();
        $tester->execute(['subcommand' => 'show', 'name' => 'nonexistent']);

        $output = $tester->getDisplay();

        $this->assertStringContainsString('Unknown template', $output);
        $this->assertSame(1, $tester->getStatusCode());
    }

    public function test_show_fails_without_name(): void
    {
        $tester = $this->makeCommandTester();
        $tester->execute(['subcommand' => 'show']);

        $output = $tester->getDisplay();

        $this->assertStringContainsString('Error', $output);
        $this->assertSame(1, $tester->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // install
    // -------------------------------------------------------------------------

    public function test_install_writes_built_in_template(): void
    {
        $tester = $this->makeCommandTester();
        $tester->execute([
            'subcommand' => 'install',
            'name'       => 'blog',
            '--output'   => $this->tmpOutput,
        ]);

        $this->assertFileExists($this->tmpOutput);
        $schema = json_decode(file_get_contents($this->tmpOutput), true);
        $this->assertIsArray($schema);
        $this->assertArrayHasKey('version', $schema);
        $this->assertArrayHasKey('models', $schema);
        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_install_shows_success_message(): void
    {
        $tester = $this->makeCommandTester();
        $tester->execute([
            'subcommand' => 'install',
            'name'       => 'crm',
            '--output'   => $this->tmpOutput,
        ]);

        $output = $tester->getDisplay();

        $this->assertStringContainsString('Installed', $output);
        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_install_fails_if_output_exists_without_force(): void
    {
        file_put_contents($this->tmpOutput, '{}');

        $tester = $this->makeCommandTester();
        $tester->execute([
            'subcommand' => 'install',
            'name'       => 'blog',
            '--output'   => $this->tmpOutput,
        ]);

        $output = $tester->getDisplay();

        $this->assertStringContainsString('already exists', $output);
        $this->assertSame(1, $tester->getStatusCode());
    }

    public function test_install_overwrites_with_force_flag(): void
    {
        file_put_contents($this->tmpOutput, '{}');

        $tester = $this->makeCommandTester();
        $tester->execute([
            'subcommand' => 'install',
            'name'       => 'blog',
            '--output'   => $this->tmpOutput,
            '--force'    => true,
        ]);

        $schema = json_decode(file_get_contents($this->tmpOutput), true);

        $this->assertIsArray($schema);
        $this->assertArrayHasKey('models', $schema);
        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_install_fails_without_source(): void
    {
        $tester = $this->makeCommandTester();
        $tester->execute(['subcommand' => 'install']);

        $output = $tester->getDisplay();

        $this->assertStringContainsString('Error', $output);
        $this->assertSame(1, $tester->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // New template stubs load correctly
    // -------------------------------------------------------------------------

    /**
     * @dataProvider newTemplateNameProvider
     */
    public function test_new_templates_load_correctly(string $name): void
    {
        $schema = TemplateRegistry::load($name);

        $this->assertArrayHasKey('version', $schema);
        $this->assertSame(1, $schema['version']);
        $this->assertArrayHasKey('app', $schema);
        $this->assertArrayHasKey('models', $schema);
        $this->assertNotEmpty($schema['models']);
    }

    public static function newTemplateNameProvider(): array
    {
        return [
            'marketplace' => ['marketplace'],
            'booking'     => ['booking'],
            'inventory'   => ['inventory'],
            'helpdesk'    => ['helpdesk'],
            'lms'         => ['lms'],
            'social'      => ['social'],
        ];
    }

    // -------------------------------------------------------------------------
    // TemplateRegistry::loadExternal
    // -------------------------------------------------------------------------

    public function test_load_external_rejects_unsupported_source(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported external template source');

        TemplateRegistry::loadExternal('ftp://example.com/schema.json');
    }

    // -------------------------------------------------------------------------
    // Unknown subcommand
    // -------------------------------------------------------------------------

    public function test_unknown_subcommand_returns_failure(): void
    {
        $tester = $this->makeCommandTester();
        $tester->execute(['subcommand' => 'bogus']);

        $output = $tester->getDisplay();

        $this->assertStringContainsString('Unknown subcommand', $output);
        $this->assertSame(1, $tester->getStatusCode());
    }
}
