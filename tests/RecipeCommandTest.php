<?php

namespace CodingSunshine\Ensemble\Tests;

use CodingSunshine\Ensemble\Console\RecipeCommand;
use CodingSunshine\Ensemble\Http\LaraPluginsClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class RecipeCommandTest extends TestCase
{
    private string $tmpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpSchema = sys_get_temp_dir().'/ensemble-recipe-test-'.uniqid().'.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpSchema)) {
            unlink($this->tmpSchema);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function writeSchema(array $schema): void
    {
        file_put_contents($this->tmpSchema, json_encode($schema, JSON_PRETTY_PRINT));
    }

    private function readSchema(): array
    {
        return json_decode(file_get_contents($this->tmpSchema), true);
    }

    private function makeCommandTester(?LaraPluginsClient $client = null): CommandTester
    {
        $app = new Application();
        $command = new RecipeCommand($client);
        $app->add($command);

        return new CommandTester($app->find('recipe'));
    }

    // -------------------------------------------------------------------------
    // list
    // -------------------------------------------------------------------------

    public function test_list_shows_built_in_recipes(): void
    {
        $tester = $this->makeCommandTester();
        $tester->execute(['subcommand' => 'list', '--schema' => $this->tmpSchema]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('roles-permissions', $output);
        $this->assertStringContainsString('spatie/laravel-permission', $output);
        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_list_shows_packages_in_schema(): void
    {
        $this->writeSchema(['version' => '1', 'recipes' => ['filament/filament', 'stancl/tenancy']]);

        $tester = $this->makeCommandTester();
        $tester->execute(['subcommand' => 'list', '--schema' => $this->tmpSchema]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('filament/filament', $output);
        $this->assertStringContainsString('stancl/tenancy', $output);
    }

    // -------------------------------------------------------------------------
    // add
    // -------------------------------------------------------------------------

    public function test_add_recipe_by_feature_key(): void
    {
        $this->writeSchema(['version' => '1', 'app' => ['name' => 'TestApp']]);

        $tester = $this->makeCommandTester();
        $tester->execute(['subcommand' => 'add', 'name' => 'roles', '--schema' => $this->tmpSchema]);

        $this->assertSame(0, $tester->getStatusCode());
        $schema = $this->readSchema();
        $this->assertContains('spatie/laravel-permission', $schema['recipes']);
    }

    public function test_add_recipe_by_full_package_name(): void
    {
        $this->writeSchema(['version' => '1', 'app' => ['name' => 'TestApp']]);

        $tester = $this->makeCommandTester();
        $tester->execute(['subcommand' => 'add', 'name' => 'laravel/cashier', '--schema' => $this->tmpSchema]);

        $this->assertSame(0, $tester->getStatusCode());
        $schema = $this->readSchema();
        $this->assertContains('laravel/cashier', $schema['recipes']);
    }

    public function test_add_does_not_duplicate_existing_recipe(): void
    {
        $this->writeSchema(['version' => '1', 'app' => ['name' => 'TestApp'], 'recipes' => ['spatie/laravel-permission']]);

        $tester = $this->makeCommandTester();
        $tester->execute(['subcommand' => 'add', 'name' => 'roles', '--schema' => $this->tmpSchema]);

        $schema = $this->readSchema();
        $this->assertCount(1, $schema['recipes']);
        $this->assertStringContainsString('Already added', $tester->getDisplay());
    }

    public function test_add_requires_name_argument(): void
    {
        $tester = $this->makeCommandTester();
        $tester->execute(['subcommand' => 'add', '--schema' => $this->tmpSchema]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Error', $tester->getDisplay());
    }

    public function test_add_fails_for_unknown_recipe_name(): void
    {
        $this->writeSchema(['version' => '1']);

        $tester = $this->makeCommandTester();
        $tester->execute(['subcommand' => 'add', 'name' => 'nonexistent-recipe', '--schema' => $this->tmpSchema]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Unknown recipe', $tester->getDisplay());
    }

    public function test_add_fails_when_schema_not_found(): void
    {
        $tester = $this->makeCommandTester();
        $tester->execute(['subcommand' => 'add', 'name' => 'roles', '--schema' => '/nonexistent/path/ensemble.json']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Schema file not found', $tester->getDisplay());
    }

    // -------------------------------------------------------------------------
    // remove
    // -------------------------------------------------------------------------

    public function test_remove_recipe_by_feature_key(): void
    {
        $this->writeSchema(['version' => '1', 'app' => ['name' => 'TestApp'], 'recipes' => ['spatie/laravel-permission', 'laravel/cashier']]);

        $tester = $this->makeCommandTester();
        $tester->execute(['subcommand' => 'remove', 'name' => 'roles', '--schema' => $this->tmpSchema]);

        $this->assertSame(0, $tester->getStatusCode());
        $schema = $this->readSchema();
        $this->assertNotContains('spatie/laravel-permission', $schema['recipes']);
        $this->assertContains('laravel/cashier', $schema['recipes']);
    }

    public function test_remove_notifies_when_package_not_in_schema(): void
    {
        $this->writeSchema(['version' => '1', 'app' => ['name' => 'TestApp'], 'recipes' => []]);

        $tester = $this->makeCommandTester();
        $tester->execute(['subcommand' => 'remove', 'name' => 'roles', '--schema' => $this->tmpSchema]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Not found in schema', $tester->getDisplay());
    }

    // -------------------------------------------------------------------------
    // search
    // -------------------------------------------------------------------------

    public function test_search_displays_results_with_health_badge(): void
    {
        $mockClient = $this->createMock(LaraPluginsClient::class);
        $mockClient->method('search')->willReturn([
            ['package_name' => 'spatie/laravel-permission', 'description' => 'Role management', 'health_score' => 'healthy'],
        ]);
        $mockClient->method('formatHealthScore')->willReturn('🟢 Healthy');

        $tester = $this->makeCommandTester($mockClient);
        $tester->execute(['subcommand' => 'search', 'name' => 'auth']);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('spatie/laravel-permission', $output);
        $this->assertStringContainsString('🟢 Healthy', $output);
        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_search_shows_no_results_message_when_empty(): void
    {
        $mockClient = $this->createMock(LaraPluginsClient::class);
        $mockClient->method('search')->willReturn([]);
        $mockClient->method('formatHealthScore')->willReturn('');

        $tester = $this->makeCommandTester($mockClient);
        $tester->execute(['subcommand' => 'search', 'name' => 'unknownxyz123']);

        $this->assertStringContainsString('No results found', $tester->getDisplay());
    }

    public function test_search_requires_query(): void
    {
        $tester = $this->makeCommandTester();
        $tester->execute(['subcommand' => 'search']);

        $this->assertSame(1, $tester->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // info
    // -------------------------------------------------------------------------

    public function test_info_shows_known_recipe_details(): void
    {
        $mockClient = $this->createMock(LaraPluginsClient::class);
        $mockClient->method('getDetails')->willReturn(null);

        $tester = $this->makeCommandTester($mockClient);
        $tester->execute(['subcommand' => 'info', 'name' => 'spatie/laravel-permission']);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('roles-permissions', $output);
        $this->assertStringContainsString('spatie/laravel-permission', $output);
        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_info_shows_live_health_badge(): void
    {
        $mockClient = $this->createMock(LaraPluginsClient::class);
        $mockClient->method('getDetails')->willReturn([
            'name' => 'spatie/laravel-permission',
            'health_score' => 'healthy',
            'downloads' => 5000000,
        ]);
        $mockClient->method('formatHealthScore')->willReturn('🟢 Healthy');

        $tester = $this->makeCommandTester($mockClient);
        $tester->execute(['subcommand' => 'info', 'name' => 'spatie/laravel-permission']);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('🟢 Healthy', $output);
        $this->assertStringContainsString('5000000', $output);
    }

    // -------------------------------------------------------------------------
    // unknown subcommand
    // -------------------------------------------------------------------------

    public function test_unknown_subcommand_returns_failure(): void
    {
        $tester = $this->makeCommandTester();
        $tester->execute(['subcommand' => 'bogus']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString("Unknown subcommand 'bogus'", $tester->getDisplay());
    }
}
