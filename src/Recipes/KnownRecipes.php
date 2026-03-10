<?php

namespace CodingSunshine\Ensemble\Recipes;

/**
 * Catalog of known Laravel package recipes.
 *
 * This class is duplicated in the ensemble Laravel package (CodingSunshine\Ensemble\Recipes\KnownRecipes).
 * When adding, removing, or updating recipe entries, sync both files.
 *
 * @see ensemble/src/Recipes/KnownRecipes.php — Laravel package counterpart
 */
class KnownRecipes
{
    private static array $recipes = [
        [
            'name' => 'roles-permissions',
            'label' => 'Roles & Permissions (spatie/laravel-permission)',
            'package' => 'spatie/laravel-permission',
            'feature_key' => 'roles-permissions',
            'description' => 'Role-based access control using Spatie Permission',
            'tags' => ['auth', 'roles', 'permissions', 'spatie'],
            'traits' => ['User' => 'Spatie\\Permission\\Traits\\HasRoles'],
            'schema_additions' => ['roles' => []],
        ],
        [
            'name' => 'saas-billing',
            'label' => 'SaaS Billing / Subscriptions (laravel/cashier)',
            'package' => 'laravel/cashier',
            'feature_key' => 'saas-billing',
            'description' => 'SaaS billing and subscriptions using Laravel Cashier',
            'tags' => ['billing', 'stripe', 'subscriptions', 'saas'],
            'traits' => ['User' => 'Laravel\\Cashier\\Billable'],
            'schema_additions' => [],
        ],
        [
            'name' => 'media-uploads',
            'label' => 'Media Uploads & Library (spatie/laravel-medialibrary)',
            'package' => 'spatie/laravel-medialibrary',
            'feature_key' => 'media-uploads',
            'description' => 'Media uploads and library management using Spatie MediaLibrary',
            'tags' => ['media', 'uploads', 'files', 'images', 'spatie'],
            'traits' => [],
            'schema_additions' => [],
        ],
        [
            'name' => 'search',
            'label' => 'Full-Text Search (laravel/scout)',
            'package' => 'laravel/scout',
            'feature_key' => 'search',
            'description' => 'Full-text search using Laravel Scout',
            'tags' => ['search', 'full-text', 'scout'],
            'traits' => [],
            'schema_additions' => [],
        ],
        [
            'name' => 'activity-log',
            'label' => 'Activity Log (spatie/laravel-activitylog)',
            'package' => 'spatie/laravel-activitylog',
            'feature_key' => 'activity-log',
            'description' => 'Activity logging using Spatie Activity Log',
            'tags' => ['activity', 'audit', 'log', 'spatie'],
            'traits' => [],
            'schema_additions' => [],
        ],
        [
            'name' => 'admin-panel',
            'label' => 'Admin Panel (filament/filament)',
            'package' => 'filament/filament',
            'feature_key' => 'admin-panel',
            'description' => 'Admin panel using Filament',
            'tags' => ['admin', 'panel', 'filament', 'crud'],
            'traits' => [],
            'schema_additions' => [],
        ],
        [
            'name' => 'multi-tenancy',
            'label' => 'Multi-Tenancy (stancl/tenancy)',
            'package' => 'stancl/tenancy',
            'feature_key' => 'multi-tenancy',
            'description' => 'Multi-tenancy support using Tenancy for Laravel',
            'tags' => ['tenancy', 'multi-tenant', 'saas'],
            'traits' => [],
            'schema_additions' => [],
        ],
        [
            'name' => 'api-auth',
            'label' => 'API Authentication (laravel/sanctum)',
            'package' => 'laravel/sanctum',
            'feature_key' => 'api-auth',
            'description' => 'API authentication using Laravel Sanctum',
            'tags' => ['api', 'auth', 'tokens', 'sanctum'],
            'traits' => [],
            'schema_additions' => [],
        ],
        [
            'name' => 'notifications',
            'label' => 'Notifications (built-in Laravel channels)',
            'package' => null,
            'feature_key' => 'notifications',
            'description' => 'Laravel notification channels',
            'tags' => ['notifications', 'mail', 'sms'],
            'traits' => [],
            'schema_additions' => [],
        ],
        [
            'name' => 'api-docs',
            'label' => 'API Docs / OpenAPI (dedoc/scramble)',
            'package' => 'dedoc/scramble',
            'feature_key' => 'api-docs',
            'description' => 'Auto-generate OpenAPI 3.1 documentation from code — no PHPDoc annotations needed. Complements Ensemble\'s OpenAPI generator.',
            'tags' => ['api', 'openapi', 'swagger', 'docs', 'documentation', 'scramble'],
            'traits' => [],
            'schema_additions' => [],
        ],
        [
            'name' => 'openapi-cli',
            'label' => 'OpenAPI CLI Commands (spatie/laravel-openapi-cli)',
            'package' => 'spatie/laravel-openapi-cli',
            'feature_key' => 'openapi-cli',
            'description' => 'Turn any OpenAPI spec into typed Artisan commands. Use with Ensemble\'s generated openapi.yaml to consume external APIs.',
            'tags' => ['api', 'openapi', 'cli', 'commands', 'spatie'],
            'traits' => [],
            'schema_additions' => [],
        ],
        [
            'name' => 'feature-flags',
            'label' => 'Feature Flags (laravel/pennant)',
            'package' => 'laravel/pennant',
            'feature_key' => 'feature-flags',
            'description' => 'Feature flags using Laravel Pennant',
            'tags' => ['features', 'flags', 'pennant', 'ab-testing'],
            'traits' => [],
            'schema_additions' => [],
        ],
        [
            'name' => 'event-sourcing',
            'label' => 'Event Sourcing / CQRS (spatie/laravel-event-sourcing)',
            'package' => 'spatie/laravel-event-sourcing',
            'feature_key' => 'event-sourcing',
            'description' => 'Event sourcing with Spatie Event Sourcing',
            'tags' => ['events', 'sourcing', 'cqrs', 'spatie'],
            'traits' => [],
            'schema_additions' => [],
        ],
        [
            'name' => 'data-transfer',
            'label' => 'Data Objects / DTOs (spatie/laravel-data)',
            'package' => 'spatie/laravel-data',
            'feature_key' => 'data-transfer',
            'description' => 'Typed data objects using Spatie Laravel Data. Pairs well with API resources and Scramble docs.',
            'tags' => ['data', 'dto', 'api', 'resources', 'spatie'],
            'traits' => [],
            'schema_additions' => [],
        ],
        [
            'name' => 'query-builder',
            'label' => 'Filterable & Sortable API Queries (spatie/laravel-query-builder)',
            'package' => 'spatie/laravel-query-builder',
            'feature_key' => 'query-builder',
            'description' => 'Filterable, sortable, and includable API queries using Spatie Query Builder',
            'tags' => ['api', 'filter', 'sort', 'include', 'spatie'],
            'traits' => [],
            'schema_additions' => [],
        ],
        [
            'name' => 'rate-limiting',
            'label' => 'Rate Limiting (built-in throttle middleware)',
            'package' => null,
            'feature_key' => 'rate-limiting',
            'description' => 'Laravel API rate limiting (built-in throttle middleware)',
            'tags' => ['api', 'rate-limit', 'throttle'],
            'traits' => [],
            'schema_additions' => [],
        ],
        [
            'name' => 'api-versioning',
            'label' => 'API Versioning via route prefix (built-in)',
            'package' => null,
            'feature_key' => 'api-versioning',
            'description' => 'API versioning via route prefix (v1, v2)',
            'tags' => ['api', 'versioning'],
            'traits' => [],
            'schema_additions' => [],
        ],
    ];

    public static function all(): array
    {
        return static::$recipes;
    }

    public static function findByFeatureKey(string $featureKey): ?array
    {
        foreach (static::$recipes as $recipe) {
            if ($recipe['feature_key'] === $featureKey) {
                return $recipe;
            }
        }

        return null;
    }

    public static function findByPackage(string $package): ?array
    {
        foreach (static::$recipes as $recipe) {
            if ($recipe['package'] === $package) {
                return $recipe;
            }
        }

        return null;
    }

    public static function searchByTag(string $tag): array
    {
        return array_values(array_filter(static::$recipes, function (array $recipe) use ($tag) {
            return in_array($tag, $recipe['tags'], true);
        }));
    }

    /**
     * Returns a map of feature_key => ['name' => ..., 'package' => ...] for use in ConversationEngine.
     */
    public static function toFeatureRecipeMap(): array
    {
        $map = [];
        foreach (static::$recipes as $recipe) {
            $map[$recipe['feature_key']] = [
                'name' => $recipe['name'],
                'package' => $recipe['package'],
            ];
        }

        return $map;
    }

    public static function toKnownPackagesMap(): array
    {
        $map = [];
        foreach (static::$recipes as $recipe) {
            if ($recipe['package'] !== null) {
                $map[$recipe['package']] = $recipe['feature_key'];
            }
        }

        return $map;
    }

    /**
     * Returns a map of feature_key => label suitable for use in interactive prompts.
     * Each label is a concise, human-readable string describing the feature.
     *
     * @return array<string, string>
     */
    public static function toPromptOptions(): array
    {
        $options = [];
        foreach (static::$recipes as $recipe) {
            $options[$recipe['feature_key']] = $recipe['label'];
        }

        return $options;
    }
}
