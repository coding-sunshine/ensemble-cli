<?php

namespace CodingSunshine\Ensemble\Tests;

use CodingSunshine\Ensemble\AI\Providers\ProviderContract;
use CodingSunshine\Ensemble\Console\AiCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class AiCommandTest extends TestCase
{
    private string $tmpSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpSchema = sys_get_temp_dir().'/ensemble-ai-test-'.uniqid().'.json';
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

    /**
     * Build a CommandTester with a mocked provider injected via AiCommand subclass.
     */
    private function makeCommandTester(array $patchResponse, bool $failFirst = false): CommandTester
    {
        $callCount = 0;
        $provider = $this->createMock(ProviderContract::class);
        $provider->method('name')->willReturn('MockProvider');
        $provider->method('ping')->willReturnCallback(function () {});
        $provider->method('estimateTokens')->willReturn(0);
        $provider->method('completeStructured')
            ->willReturnCallback(function () use ($patchResponse, $failFirst, &$callCount) {
                $callCount++;
                if ($failFirst && $callCount === 1) {
                    // Return an invalid patch on first call (missing required field)
                    return ['models' => ['BadModel' => ['columns' => []]]];
                }

                return $patchResponse;
            });

        $command = new class ($provider) extends AiCommand {
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
        $tester = new CommandTester($app->find('ai'));

        return $tester;
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    public function test_apply_flag_writes_schema_without_confirmation(): void
    {
        $this->writeSchema([
            'version' => 1,
            'app'     => ['name' => 'TestApp', 'stack' => 'livewire'],
            'models'  => [
                'Order' => ['columns' => ['total' => 'decimal']],
            ],
        ]);

        $patch = [
            'models' => [
                'Order' => ['columns' => ['status' => 'string:nullable']],
            ],
        ];

        $tester = $this->makeCommandTester($patch);
        $status = $tester->execute([
            'prompt'   => 'add a status field to orders',
            '--schema' => $this->tmpSchema,
            '--apply'  => true,
        ]);

        $this->assertSame(0, $status);

        $written = $this->readSchema();
        $this->assertArrayHasKey('status', $written['models']['Order']['columns']);
    }

    public function test_dry_run_shows_diff_without_writing(): void
    {
        $original = [
            'version' => 1,
            'app'     => ['name' => 'TestApp', 'stack' => 'livewire'],
            'models'  => [
                'Post' => ['columns' => ['title' => 'string']],
            ],
        ];

        $this->writeSchema($original);

        $patch = [
            'models' => [
                'Comment' => ['columns' => ['body' => 'text']],
            ],
        ];

        $tester = $this->makeCommandTester($patch);
        $status = $tester->execute([
            'prompt'    => 'add a Comment model',
            '--schema'  => $this->tmpSchema,
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $status);

        // Schema file must remain unchanged
        $after = $this->readSchema();
        $this->assertArrayNotHasKey('Comment', $after['models'] ?? []);

        // Output must mention dry run
        $this->assertStringContainsString('Dry run', $tester->getDisplay());
    }

    public function test_json_flag_outputs_machine_readable_result(): void
    {
        $this->writeSchema([
            'version' => 1,
            'app'     => ['name' => 'TestApp', 'stack' => 'react'],
            'models'  => [
                'Product' => ['columns' => ['name' => 'string']],
            ],
        ]);

        $patch = [
            'models' => [
                'Category' => ['columns' => ['name' => 'string']],
            ],
        ];

        $tester = $this->makeCommandTester($patch);
        $status = $tester->execute([
            'prompt'   => 'add a Category model',
            '--schema' => $this->tmpSchema,
            '--json'   => true,
            '--apply'  => true,
        ]);

        $this->assertSame(0, $status);

        $decoded = json_decode($tester->getDisplay(), true);
        $this->assertNotNull($decoded, 'Output must be valid JSON');
        $this->assertTrue($decoded['success']);
        $this->assertArrayHasKey('added', $decoded);
        $this->assertArrayHasKey('modified', $decoded);
        $this->assertArrayHasKey('removed', $decoded);
        $this->assertArrayHasKey('schema', $decoded);
    }

    public function test_fails_when_schema_file_not_found(): void
    {
        $tester = $this->makeCommandTester([]);
        $status = $tester->execute([
            'prompt'   => 'add something',
            '--schema' => '/tmp/does-not-exist-ensemble.json',
        ]);

        $this->assertSame(1, $status);
    }

    public function test_json_flag_returns_failure_when_schema_missing(): void
    {
        $tester = $this->makeCommandTester([]);
        $status = $tester->execute([
            'prompt'   => 'add something',
            '--schema' => '/tmp/does-not-exist-ensemble.json',
            '--json'   => true,
        ]);

        $this->assertSame(1, $status);

        $decoded = json_decode($tester->getDisplay(), true);
        $this->assertNotNull($decoded, 'Output must be valid JSON');
        $this->assertFalse($decoded['success']);
        $this->assertArrayHasKey('error', $decoded);
    }

    public function test_patch_with_new_model_adds_to_schema(): void
    {
        $this->writeSchema([
            'version' => 1,
            'app'     => ['name' => 'BlogApp', 'stack' => 'livewire'],
            'models'  => [
                'Post' => ['columns' => ['title' => 'string', 'body' => 'text']],
            ],
        ]);

        $patch = [
            'models' => [
                'Tag' => [
                    'columns'       => ['name' => 'string:unique'],
                    'relationships' => ['posts' => 'belongsToMany'],
                ],
            ],
        ];

        $tester = $this->makeCommandTester($patch);
        $tester->execute([
            'prompt'   => 'add a Tag model with many-to-many relation to Post',
            '--schema' => $this->tmpSchema,
            '--apply'  => true,
        ]);

        $written = $this->readSchema();
        $this->assertArrayHasKey('Tag', $written['models']);
        $this->assertSame('string:unique', $written['models']['Tag']['columns']['name']);
    }
}
