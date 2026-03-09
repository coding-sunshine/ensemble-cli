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
            'package' => 'spatie/laravel-permission',
            'feature_key' => 'roles-permissions',
            'description' => 'Role-based access control using Spatie Permission',
            'tags' => ['auth', 'roles', 'permissions', 'spatie'],
            'traits' => ['User' => 'Spatie\\Permission\\Traits\\HasRoles'],
            'schema_additions' => ['roles' => []],
        ],
        [
            'name' => 'saas-billing',
            'package' => 'laravel/cashier',
            'feature_key' => 'saas-billing',
            'description' => 'SaaS billing and subscriptions using Laravel Cashier',
            'tags' => ['billing', 'stripe', 'subscriptions', 'saas'],
            'traits' => ['User' => 'Laravel\\Cashier\\Billable'],
            'schema_additions' => [],
        ],
        [
            'name' => 'media-uploads',
            'package' => 'spatie/laravel-medialibrary',
            'feature_key' => 'media-uploads',
            'description' => 'Media uploads and library management using Spatie MediaLibrary',
            'tags' => ['media', 'uploads', 'files', 'images', 'spatie'],
            'traits' => [],
            'schema_additions' => [],
        ],
        [
            'name' => 'search',
            'package' => 'laravel/scout',
            'feature_key' => 'search',
            'description' => 'Full-text search using Laravel Scout',
            'tags' => ['search', 'full-text', 'scout'],
            'traits' => [],
            'schema_additions' => [],
        ],
        [
            'name' => 'activity-log',
            'package' => 'spatie/laravel-activitylog',
            'feature_key' => 'activity-log',
            'description' => 'Activity logging using Spatie Activity Log',
            'tags' => ['activity', 'audit', 'log', 'spatie'],
            'traits' => [],
            'schema_additions' => [],
        ],
        [
            'name' => 'admin-panel',
            'package' => 'filament/filament',
            'feature_key' => 'admin-panel',
            'description' => 'Admin panel using Filament',
            'tags' => ['admin', 'panel', 'filament', 'crud'],
            'traits' => [],
            'schema_additions' => [],
        ],
        [
            'name' => 'multi-tenancy',
            'package' => 'stancl/tenancy',
            'feature_key' => 'multi-tenancy',
            'description' => 'Multi-tenancy support using Tenancy for Laravel',
            'tags' => ['tenancy', 'multi-tenant', 'saas'],
            'traits' => [],
            'schema_additions' => [],
        ],
        [
            'name' => 'api-auth',
            'package' => 'laravel/sanctum',
            'feature_key' => 'api-auth',
            'description' => 'API authentication using Laravel Sanctum',
            'tags' => ['api', 'auth', 'tokens', 'sanctum'],
            'traits' => [],
            'schema_additions' => [],
        ],
        [
            'name' => 'notifications',
            'package' => null,
            'feature_key' => 'notifications',
            'description' => 'Laravel notification channels',
            'tags' => ['notifications', 'mail', 'sms'],
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
}
