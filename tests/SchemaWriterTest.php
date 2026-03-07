<?php

namespace CodingSunshine\Ensemble\Tests;

use CodingSunshine\Ensemble\Schema\SchemaWriter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SchemaWriterTest extends TestCase
{
    protected string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/ensemble-schema-test-'.uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempDir.'/*') ?: []);
        @rmdir($this->tempDir);
    }

    public function test_write_creates_json_file(): void
    {
        $path = $this->tempDir.'/ensemble.json';
        $schema = [
            'app' => ['name' => 'test-app', 'stack' => 'livewire'],
            'models' => [],
        ];

        SchemaWriter::write($path, $schema);

        $this->assertFileExists($path);
        $contents = json_decode(file_get_contents($path), true);
        $this->assertSame('test-app', $contents['app']['name']);
    }

    public function test_write_adds_version_if_missing(): void
    {
        $path = $this->tempDir.'/ensemble.json';
        SchemaWriter::write($path, ['app' => ['name' => 'test', 'stack' => 'livewire']]);

        $contents = json_decode(file_get_contents($path), true);
        $this->assertArrayHasKey('version', $contents);
        $this->assertSame(1, $contents['version']);
    }

    public function test_write_reorders_keys(): void
    {
        $path = $this->tempDir.'/ensemble.json';
        SchemaWriter::write($path, [
            'recipes' => [],
            'models' => [],
            'app' => ['name' => 'test', 'stack' => 'livewire'],
        ]);

        $contents = file_get_contents($path);
        $keys = array_keys(json_decode($contents, true));
        $this->assertSame('version', $keys[0]);
        $this->assertSame('app', $keys[1]);
    }

    public function test_read_returns_valid_schema(): void
    {
        $path = $this->tempDir.'/ensemble.json';
        $schema = ['version' => 1, 'app' => ['name' => 'test', 'stack' => 'livewire']];
        file_put_contents($path, json_encode($schema));

        $result = SchemaWriter::read($path);
        $this->assertSame('test', $result['app']['name']);
    }

    public function test_read_throws_for_missing_file(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Schema file not found');
        SchemaWriter::read($this->tempDir.'/nonexistent.json');
    }

    public function test_read_throws_for_yaml_files(): void
    {
        $path = $this->tempDir.'/schema.yaml';
        file_put_contents($path, 'test: true');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('YAML is not supported');
        SchemaWriter::read($path);
    }

    public function test_read_throws_for_invalid_json(): void
    {
        $path = $this->tempDir.'/bad.json';
        file_put_contents($path, '{invalid json}');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON');
        SchemaWriter::read($path);
    }

    public function test_read_throws_for_newer_schema_version(): void
    {
        $path = $this->tempDir.'/future.json';
        file_put_contents($path, json_encode([
            'version' => 999,
            'app' => ['name' => 'test', 'stack' => 'livewire'],
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('newer than this CLI supports');
        SchemaWriter::read($path);
    }

    public function test_read_validates_schema_structure(): void
    {
        $path = $this->tempDir.'/bad-schema.json';
        file_put_contents($path, json_encode([
            'version' => 1,
            'models' => 'not-an-object',
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Schema validation failed');
        SchemaWriter::read($path);
    }

    public function test_write_creates_directory_if_needed(): void
    {
        $path = $this->tempDir.'/sub/dir/ensemble.json';
        SchemaWriter::write($path, ['app' => ['name' => 'test', 'stack' => 'livewire']]);
        $this->assertFileExists($path);

        unlink($path);
        @rmdir(dirname($path));
        @rmdir(dirname(dirname($path)));
    }
}
