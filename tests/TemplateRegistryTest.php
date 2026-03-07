<?php

namespace CodingSunshine\Ensemble\Tests;

use CodingSunshine\Ensemble\Schema\TemplateRegistry;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TemplateRegistryTest extends TestCase
{
    public function test_names_returns_all_template_names(): void
    {
        $names = TemplateRegistry::names();
        $this->assertContains('saas', $names);
        $this->assertContains('blog', $names);
        $this->assertContains('ecommerce', $names);
        $this->assertContains('crm', $names);
        $this->assertContains('api', $names);
    }

    public function test_exists_returns_true_for_valid_template(): void
    {
        $this->assertTrue(TemplateRegistry::exists('saas'));
        $this->assertTrue(TemplateRegistry::exists('blog'));
    }

    public function test_exists_returns_false_for_unknown_template(): void
    {
        $this->assertFalse(TemplateRegistry::exists('nonexistent'));
        $this->assertFalse(TemplateRegistry::exists(''));
    }

    public function test_load_returns_valid_schema_array(): void
    {
        $schema = TemplateRegistry::load('saas');
        $this->assertIsArray($schema);
        $this->assertArrayHasKey('version', $schema);
        $this->assertArrayHasKey('app', $schema);
        $this->assertArrayHasKey('models', $schema);
    }

    public function test_load_throws_for_unknown_template(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown template');
        TemplateRegistry::load('nonexistent');
    }

    public function test_options_returns_associative_array(): void
    {
        $options = TemplateRegistry::options();
        $this->assertIsArray($options);
        $this->assertArrayHasKey('saas', $options);
        $this->assertIsString($options['saas']);
    }

    /**
     * @dataProvider templateNameProvider
     */
    public function test_all_templates_load_successfully(string $name): void
    {
        $schema = TemplateRegistry::load($name);
        $this->assertArrayHasKey('version', $schema);
        $this->assertSame(1, $schema['version']);
        $this->assertArrayHasKey('app', $schema);
        $this->assertArrayHasKey('name', $schema['app']);
        $this->assertArrayHasKey('stack', $schema['app']);
    }

    public static function templateNameProvider(): array
    {
        return [
            'saas' => ['saas'],
            'blog' => ['blog'],
            'ecommerce' => ['ecommerce'],
            'crm' => ['crm'],
            'api' => ['api'],
        ];
    }
}
