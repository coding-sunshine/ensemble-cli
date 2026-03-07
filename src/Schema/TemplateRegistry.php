<?php

namespace CodingSunshine\Ensemble\Schema;

use RuntimeException;

class TemplateRegistry
{
    /**
     * @var array<string, string> Map of template name → description.
     */
    protected const TEMPLATES = [
        'saas' => 'SaaS starter (teams, subscriptions, billing)',
        'blog' => 'Blog platform (posts, categories, tags, comments)',
        'ecommerce' => 'E-commerce store (products, orders, reviews)',
        'crm' => 'CRM (contacts, companies, deals, activities)',
        'api' => 'API service (tokens, webhooks, rate limiting)',
    ];

    /**
     * Get the select-prompt options for template selection.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return self::TEMPLATES;
    }

    /**
     * Get all available template names.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_keys(self::TEMPLATES);
    }

    /**
     * Check whether a template name is valid.
     */
    public static function exists(string $name): bool
    {
        return isset(self::TEMPLATES[$name]);
    }

    /**
     * Load a bundled template schema by name.
     *
     * @return array<string, mixed>
     */
    public static function load(string $name): array
    {
        if (! self::exists($name)) {
            throw new RuntimeException(
                "Unknown template \"{$name}\". Available: ".implode(', ', self::names())
            );
        }

        $path = self::templatePath($name);

        return SchemaWriter::read($path);
    }

    protected static function templatePath(string $name): string
    {
        return dirname(__DIR__, 2)."/stubs/templates/{$name}.json";
    }
}
