<?php

namespace CodingSunshine\Ensemble\Tests;

use CodingSunshine\Ensemble\AI\Providers\ProviderContract;
use CodingSunshine\Ensemble\Console\UpdateCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class UpdateCommandTest extends TestCase
{
    private string $tmpDir;

    private string $schemaPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = sys_get_temp_dir().'/ensemble-update-test-'.uniqid();
        mkdir($this->tmpDir, 0755, true);
        $this->schemaPath = $this->tmpDir.'/ensemble.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->schemaPath)) {
            unlink($this->schemaPath);
        }

        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function writeSchema(array $schema): void
    {
        file_put_contents($this->schemaPath, json_encode($schema, JSON_PRETTY_PRINT));
    }

    private function readSchema(): array
    {
        return json_decode(file_get_contents($this->schemaPath), true);
    }

    /**
     * Build a CommandTester with a mocked provider injected via UpdateCommand subclass.
     */
    private function makeCommandTester(array $patchResponse): CommandTester
    {
        $provider = $this->createMock(ProviderContract::class);
        $provider->method('name')->willReturn('MockProvider');
        $provider->method('ping')->willReturnCallback(function () {});
        $provider->method('estimateTokens')->willReturn(0);
        $provider->method('completeStructured')->willReturn($patchResponse);

        $command = new class ($provider) extends UpdateCommand {
            public function __construct(private readonly ProviderContract $mockProvider)
            {
                parent::__construct();
            }

            protected function resolveProvider(\Symfony\Component\Console\Input\InputInterface $input): ProviderContract
            {
                return $this->mockProvider;
            }
        };

        $app = new Application();
        $app->add($command);

        return new CommandTester($app->find('update'));
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    public function test_updates_schema_with_prompt_option(): void
    {
        $this->writeSchema([
            'version' => 1,
            'app'     => ['name' => 'MyApp', 'stack' => 'livewire'],
            'models'  => [
                'Post' => ['columns' => ['title' => 'string']],
            ],
        ]);

        $patch = [
            'models' => [
                'Comment' => ['columns' => ['body' => 'text']],
            ],
        ];

        $tester = $this->makeCommandTester($patch);
        $status = $tester->execute([
            'directory' => $this->tmpDir,
            '--prompt'  => 'add a Comment model',
        ]);

        $this->assertSame(0, $status);

        $written = $this->readSchema();
        $this->assertArrayHasKey('Comment', $written['models']);
        $this->assertSame('text', $written['models']['Comment']['columns']['body']);
    }

    public function test_fails_when_no_ensemble_json_in_directory(): void
    {
        $emptyDir = sys_get_temp_dir().'/ensemble-update-missing-'.uniqid();
        mkdir($emptyDir, 0755, true);

        $tester = $this->makeCommandTester([]);
        $status = $tester->execute([
            'directory' => $emptyDir,
            '--prompt'  => 'add something',
        ]);

        rmdir($emptyDir);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('No ensemble.json found', $tester->getDisplay());
    }

    public function test_writes_schema_and_shows_diff(): void
    {
        $this->writeSchema([
            'version' => 1,
            'app'     => ['name' => 'ShopApp', 'stack' => 'react'],
            'models'  => [
                'Product' => ['columns' => ['name' => 'string', 'price' => 'decimal']],
            ],
        ]);

        $patch = [
            'models' => [
                'Category' => ['columns' => ['name' => 'string']],
            ],
        ];

        $tester = $this->makeCommandTester($patch);
        $status = $tester->execute([
            'directory' => $this->tmpDir,
            '--prompt'  => 'add a Category model',
        ]);

        $this->assertSame(0, $status);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Schema updated', $display);

        $written = $this->readSchema();
        $this->assertArrayHasKey('Category', $written['models']);
    }

    public function test_no_changes_detected_message_when_patch_is_empty(): void
    {
        $this->writeSchema([
            'version' => 1,
            'app'     => ['name' => 'MyApp', 'stack' => 'livewire'],
            'models'  => [],
        ]);

        $tester = $this->makeCommandTester([]);
        $status = $tester->execute([
            'directory' => $this->tmpDir,
            '--prompt'  => 'nothing to change',
        ]);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('No schema changes detected', $tester->getDisplay());
    }

    public function test_build_flag_is_accepted_as_option(): void
    {
        $this->writeSchema([
            'version' => 1,
            'app'     => ['name' => 'MyApp', 'stack' => 'livewire'],
            'models'  => [],
        ]);

        // We override runBuild to avoid actually calling artisan
        $provider = $this->createMock(ProviderContract::class);
        $provider->method('name')->willReturn('MockProvider');
        $provider->method('ping')->willReturnCallback(function () {});
        $provider->method('estimateTokens')->willReturn(0);
        $provider->method('completeStructured')->willReturn([]);

        $command = new class ($provider) extends UpdateCommand {
            public bool $buildWasCalled = false;

            public function __construct(private readonly ProviderContract $mockProvider)
            {
                parent::__construct();
            }

            protected function resolveProvider(\Symfony\Component\Console\Input\InputInterface $input): ProviderContract
            {
                return $this->mockProvider;
            }

            protected function runBuild(string $directory, \Symfony\Component\Console\Output\OutputInterface $output): int
            {
                $this->buildWasCalled = true;

                return \Symfony\Component\Console\Command\Command::SUCCESS;
            }
        };

        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('update'));

        $status = $tester->execute([
            'directory' => $this->tmpDir,
            '--prompt'  => 'no-op',
            '--build'   => true,
        ]);

        $this->assertSame(0, $status);
        $this->assertTrue($command->buildWasCalled);
    }
}
