<?php

namespace CodingSunshine\Ensemble\Schema;

use CodingSunshine\Ensemble\AI\ConversationEngine;
use RuntimeException;

/**
 * Reads and writes ensemble.json with version validation and consistent key ordering.
 *
 * This class is duplicated in the ensemble Laravel package (CodingSunshine\Ensemble\SchemaWriter).
 * The ensemble package version is write-only; this version also handles reading + version validation.
 * When changing key ordering or JSON encoding flags, sync both files.
 *
 * @see ensemble/src/SchemaWriter.php — Laravel package counterpart
 */
class SchemaWriter
{
    /**
     * Write the schema array to a JSON file with pretty-printing.
     * Ensures the version field is always present.
     *
     * @param  array<string, mixed>  $schema
     */
    public static function write(string $path, array $schema): void
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (! isset($schema['version'])) {
            $schema['version'] = ConversationEngine::SCHEMA_VERSION;
        }

        $ordered = self::reorderKeys($schema);

        $json = json_encode($ordered, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new RuntimeException('Failed to encode schema to JSON: '.json_last_error_msg());
        }

        file_put_contents($path, $json."\n");
    }

    /**
     * Read and decode a JSON schema file. Validates version compatibility.
     *
     * @return array<string, mixed>
     */
    public static function read(string $path): array
    {
        if (! file_exists($path)) {
            throw new RuntimeException("Schema file not found: {$path}");
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, ['yaml', 'yml'])) {
            throw new RuntimeException(
                'YAML is not supported. Please use ensemble.json instead.'
            );
        }

        $contents = file_get_contents($path);

        $schema = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                "Invalid JSON in {$path}: ".json_last_error_msg()
            );
        }

        self::validateVersion($schema, $path);
        self::validateStructure($schema, $path);

        return $schema;
    }

    /**
     * Check that the schema version is compatible with this CLI version.
     *
     * @param  array<string, mixed>  $schema
     */
    protected static function validateVersion(array $schema, string $path): void
    {
        $version = $schema['version'] ?? null;

        if ($version === null) {
            return;
        }

        if ($version > ConversationEngine::SCHEMA_VERSION) {
            throw new RuntimeException(
                "Schema version {$version} in {$path} is newer than this CLI supports (version ".ConversationEngine::SCHEMA_VERSION.'). '
                .'Please update ensemble-cli: composer global update coding-sunshine/ensemble-cli'
            );
        }
    }

    /**
     * Run structural validation and emit warnings for any issues found.
     *
     * @param  array<string, mixed>  $schema
     */
    protected static function validateStructure(array $schema, string $path): void
    {
        $validator = new SchemaValidator();
        $validator->validate($schema);

        $errors = $validator->errors();

        if (! empty($errors)) {
            throw new RuntimeException(
                "Schema validation failed for {$path}:\n  - ".implode("\n  - ", $errors)
            );
        }
    }

    /**
     * Read and decode a JSON schema file, validating only version compatibility.
     * Unlike read(), structural validation is skipped so AI commands can operate
     * on schemas that are not yet valid (e.g. to patch and fix them).
     *
     * @return array<string, mixed>
     * @throws RuntimeException on invalid JSON or incompatible schema version
     */
    public static function readLoose(string $path): array
    {
        if (! file_exists($path)) {
            throw new RuntimeException("Schema file not found: {$path}");
        }

        $contents = file_get_contents($path);
        $schema   = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Invalid JSON in {$path}: ".json_last_error_msg());
        }

        self::validateVersion($schema, $path);

        return $schema;
    }

    /**
     * Reorder schema keys so version and app come first for readability.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    protected static function reorderKeys(array $schema): array
    {
        $priority = ['version', 'app', 'models', 'controllers', 'pages', 'notifications', 'workflows', 'dashboards', 'roles', 'services', 'schedules', 'broadcasts', 'recipes'];
        $ordered = [];

        foreach ($priority as $key) {
            if (array_key_exists($key, $schema)) {
                $ordered[$key] = $schema[$key];
            }
        }

        foreach ($schema as $key => $value) {
            if (! array_key_exists($key, $ordered)) {
                $ordered[$key] = $value;
            }
        }

        return $ordered;
    }
}
