<?php

namespace CodingSunshine\Ensemble\Schema;

use RuntimeException;

class TemplateRegistry
{
    /**
     * @var array<string, string> Map of template name → description.
     */
    protected const TEMPLATES = [
        'saas'               => 'SaaS starter (teams, subscriptions, billing)',
        'blog'               => 'Blog platform (posts, categories, tags, comments)',
        'ecommerce'          => 'E-commerce store (products, orders, reviews)',
        'crm'                => 'CRM (contacts, companies, deals, activities)',
        'api'                => 'API service (tokens, webhooks, rate limiting)',
        'project-management' => 'Project management (workspaces, boards, tasks, time tracking)',
        'marketplace'        => 'Multi-vendor marketplace (vendors, products, orders)',
        'booking'            => 'Booking & scheduling (services, providers, appointments)',
        'inventory'          => 'Inventory management (warehouses, stock, purchase orders)',
        'helpdesk'           => 'Help desk (tickets, replies, knowledge base)',
        'lms'                => 'Learning management system (courses, lessons, enrollments)',
        'social'             => 'Social app (profiles, posts, follows, messaging)',
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

    /**
     * Load an external template from a source string.
     *
     * Supports:
     *  - `github:user/repo`  → fetches ensemble.json from the repo's default branch
     *  - A direct HTTPS URL  → fetches the URL directly
     *
     * Results are cached in ~/.ensemble/cache/templates/{sha256}.json for 1 hour.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException when the source cannot be resolved or the JSON is invalid.
     */
    public static function loadExternal(string $source): array
    {
        $url = self::resolveExternalUrl($source);
        $cacheKey = hash('sha256', $url);
        $cachePath = self::externalCachePath($cacheKey);

        // Return cached result if still fresh (< 1 hour old).
        if (file_exists($cachePath) && (time() - filemtime($cachePath)) < 3600) {
            $cached = file_get_contents($cachePath);
            $decoded = json_decode($cached, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $contents = self::fetchUrl($url);

        if ($contents === false || $contents === '') {
            throw new RuntimeException("Failed to fetch external template from: {$url}");
        }

        $schema = json_decode($contents, true);

        if (! is_array($schema)) {
            throw new RuntimeException("Invalid JSON returned from external template source: {$url}");
        }

        // Cache the result.
        $cacheDir = dirname($cachePath);

        if (! is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        file_put_contents($cachePath, $contents);

        return $schema;
    }

    /**
     * List all cached external template sources.
     *
     * @return array<int, array{source: string, cached_at: int}>
     */
    public static function cachedExternalTemplates(): array
    {
        $cacheDir = dirname(self::externalCachePath('placeholder'));

        if (! is_dir($cacheDir)) {
            return [];
        }

        $results = [];
        $files = glob($cacheDir.'/*.json') ?: [];

        foreach ($files as $file) {
            // Each cache file stores the raw JSON which may have a '_source' meta key.
            $contents = file_get_contents($file);
            $data = json_decode($contents, true);

            if (! is_array($data)) {
                continue;
            }

            $results[] = [
                'source'    => $data['_source'] ?? basename($file, '.json'),
                'cached_at' => (int) filemtime($file),
            ];
        }

        return $results;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    protected static function templatePath(string $name): string
    {
        return dirname(__DIR__, 2)."/stubs/templates/{$name}.json";
    }

    protected static function externalCachePath(string $key): string
    {
        $home = self::homeDirectory();

        return "{$home}/.ensemble/cache/templates/{$key}.json";
    }

    /**
     * Resolve a source string to a fetchable URL.
     */
    protected static function resolveExternalUrl(string $source): string
    {
        // github:user/repo  →  raw GitHub URL for ensemble.json on HEAD
        if (str_starts_with($source, 'github:')) {
            $repo = substr($source, strlen('github:'));

            return "https://raw.githubusercontent.com/{$repo}/HEAD/ensemble.json";
        }

        // Direct URL
        if (str_starts_with($source, 'https://') || str_starts_with($source, 'http://')) {
            return $source;
        }

        throw new RuntimeException(
            "Unsupported external template source \"{$source}\". ".
            'Supported formats: github:user/repo, https://...'
        );
    }

    /**
     * Perform an HTTP GET request and return the body, or false on failure.
     *
     * @return string|false
     */
    protected static function fetchUrl(string $url)
    {
        $context = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'header'          => "User-Agent: ensemble-cli/1.0\r\n",
                'timeout'         => 10,
                'ignore_errors'   => true,
            ],
        ]);

        return @file_get_contents($url, false, $context);
    }

    protected static function homeDirectory(): string
    {
        return $_SERVER['HOME'] ?? (($_SERVER['HOMEDRIVE'] ?? '').($_SERVER['HOMEPATH'] ?? '')) ?: sys_get_temp_dir();
    }
}
