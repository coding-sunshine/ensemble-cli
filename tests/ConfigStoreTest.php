<?php

namespace CodingSunshine\Ensemble\Tests;

use CodingSunshine\Ensemble\Config\ConfigStore;
use PHPUnit\Framework\TestCase;

class ConfigStoreTest extends TestCase
{
    protected string $tempPath;

    protected function setUp(): void
    {
        $this->tempPath = sys_get_temp_dir().'/ensemble-test-'.uniqid().'/config.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempPath)) {
            unlink($this->tempPath);
            @rmdir(dirname($this->tempPath));
        }
    }

    public function test_get_returns_default_when_empty(): void
    {
        $store = new ConfigStore($this->tempPath);
        $this->assertNull($store->get('nonexistent'));
        $this->assertSame('fallback', $store->get('nonexistent', 'fallback'));
    }

    public function test_set_and_get_simple_value(): void
    {
        $store = new ConfigStore($this->tempPath);
        $store->set('provider', 'anthropic');
        $this->assertSame('anthropic', $store->get('provider'));
    }

    public function test_set_and_get_dot_notated_value(): void
    {
        $store = new ConfigStore($this->tempPath);
        $store->set('providers.anthropic.api_key', 'sk-test-123');
        $this->assertSame('sk-test-123', $store->get('providers.anthropic.api_key'));
    }

    public function test_has_returns_correct_boolean(): void
    {
        $store = new ConfigStore($this->tempPath);
        $this->assertFalse($store->has('provider'));
        $store->set('provider', 'openai');
        $this->assertTrue($store->has('provider'));
    }

    public function test_all_returns_full_config(): void
    {
        $store = new ConfigStore($this->tempPath);
        $store->set('provider', 'ollama');
        $store->set('model', 'llama3.1');
        $all = $store->all();
        $this->assertSame('ollama', $all['provider']);
        $this->assertSame('llama3.1', $all['model']);
    }

    public function test_persists_to_disk_and_reloads(): void
    {
        $store = new ConfigStore($this->tempPath);
        $store->set('default_provider', 'anthropic');

        $store2 = new ConfigStore($this->tempPath);
        $this->assertSame('anthropic', $store2->get('default_provider'));
    }

    public function test_creates_directory_if_missing(): void
    {
        $deepPath = sys_get_temp_dir().'/ensemble-deep-'.uniqid().'/sub/config.json';
        $store = new ConfigStore($deepPath);
        $store->set('test', true);

        $this->assertFileExists($deepPath);

        unlink($deepPath);
        @rmdir(dirname($deepPath));
        @rmdir(dirname(dirname($deepPath)));
    }

    public function test_empty_file_returns_empty_array(): void
    {
        $store = new ConfigStore($this->tempPath);
        $this->assertSame([], $store->all());
    }
}
