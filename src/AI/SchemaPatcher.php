<?php

namespace CodingSunshine\Ensemble\AI;

use RuntimeException;

class SchemaPatcher
{
    /** @var string[] Sections that map keywords to schema keys */
    private const KEYWORD_MAP = [
        'model'        => 'models',
        'models'       => 'models',
        'controller'   => 'controllers',
        'controllers'  => 'controllers',
        'page'         => 'pages',
        'pages'        => 'pages',
        'notification' => 'notifications',
        'notifications'=> 'notifications',
        'workflow'     => 'workflows',
        'workflows'    => 'workflows',
        'dashboard'    => 'dashboards',
        'dashboards'   => 'dashboards',
        'role'         => 'roles',
        'roles'        => 'roles',
        'permission'   => 'roles',
        'service'      => 'services',
        'services'     => 'services',
        'schedule'     => 'schedules',
        'schedules'    => 'schedules',
        'broadcast'    => 'broadcasts',
        'broadcasts'   => 'broadcasts',
        'recipe'       => 'recipes',
        'recipes'      => 'recipes',
        'import'       => 'imports',
        'imports'      => 'imports',
    ];

    /**
     * Build a delta prompt that sends only the relevant schema section(s) to the AI.
     *
     * When a single model is targeted, sends ~200 tokens instead of ~3000 for the full schema.
     *
     * @param  array<string, mixed>  $fullSchema
     */
    public function buildDeltaPrompt(array $fullSchema, string $request, ?string $targetModel = null): string
    {
        $contextSchema = $this->extractContext($fullSchema, $request, $targetModel);
        $contextJson   = json_encode($contextSchema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $prompt = <<<PROMPT
        You are modifying an existing Laravel Ensemble schema. Return ONLY the changed sections as a partial JSON patch — do not return the full schema, only the keys you are adding or modifying.

        **Current schema context:**
        ```json
        {$contextJson}
        ```

        **Change requested:** {$request}

        Return a partial JSON object containing only the sections you need to add or modify. Do not repeat unchanged data.
        PROMPT;

        return $prompt;
    }

    /**
     * Deep-merge a patch into the full schema. The existing (local) schema wins on scalar conflicts.
     *
     * @param  array<string, mixed>  $fullSchema
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    public function applyPatch(array $fullSchema, array $patch): array
    {
        return $this->deepMerge($fullSchema, $patch);
    }

    /**
     * Compute the difference between two schemas.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{added: array<string, mixed>, modified: array<string, mixed>, removed: array<string, mixed>}
     */
    public function diff(array $before, array $after): array
    {
        $added    = [];
        $modified = [];
        $removed  = [];

        // Keys in after but not in before
        foreach ($after as $key => $value) {
            if (! array_key_exists($key, $before)) {
                $added[$key] = $value;
            } elseif ($value !== $before[$key]) {
                $modified[$key] = ['before' => $before[$key], 'after' => $value];
            }
        }

        // Keys in before but not in after
        foreach ($before as $key => $value) {
            if (! array_key_exists($key, $after)) {
                $removed[$key] = $value;
            }
        }

        return compact('added', 'modified', 'removed');
    }

    /**
     * Detect which schema sections a natural language request targets.
     *
     * Returns null if the request seems to touch multiple/all sections.
     *
     * @return string[]|null
     */
    public function detectTargetSections(string $request): ?array
    {
        $request = strtolower($request);
        $found   = [];

        foreach (self::KEYWORD_MAP as $keyword => $section) {
            if (str_contains($request, $keyword) && ! in_array($section, $found, true)) {
                $found[] = $section;
            }
        }

        return empty($found) ? null : array_values($found);
    }

    /**
     * Validate that a schema's imports array has no circular references.
     *
     * Each import string is treated as a unique file identifier.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, array<string, mixed>>  $registry  All resolved schemas keyed by identifier
     * @param  string[]  $chain  Current import chain for cycle detection
     *
     * @throws RuntimeException with descriptive cycle path on circular import
     */
    public function detectCircularImports(array $schema, array $registry = [], array $chain = [], string $currentId = 'root'): void
    {
        if (in_array($currentId, $chain, true)) {
            $cycle   = implode(' → ', [...$chain, $currentId]);
            throw new RuntimeException("Circular import detected: {$cycle}");
        }

        $imports = $schema['imports'] ?? [];

        if (empty($imports)) {
            return;
        }

        $chain[] = $currentId;

        foreach ($imports as $importId) {
            if (! is_string($importId)) {
                continue;
            }

            if (! isset($registry[$importId])) {
                // Unknown import — cannot trace further, skip
                continue;
            }

            $this->detectCircularImports($registry[$importId], $registry, $chain, $importId);
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Extract only the relevant schema context for a delta prompt.
     *
     * @param  array<string, mixed>  $fullSchema
     * @return array<string, mixed>
     */
    private function extractContext(array $fullSchema, string $request, ?string $targetModel): array
    {
        // If targeting a specific model, return only that model definition
        if ($targetModel !== null) {
            $modelKey = $this->normalizeModelKey($targetModel, $fullSchema);

            if ($modelKey !== null) {
                return [
                    'models' => [$modelKey => $fullSchema['models'][$modelKey]],
                ];
            }
        }

        // Detect relevant sections from the request text
        $sections = $this->detectTargetSections($request);

        if ($sections === null) {
            // Cannot narrow down — return minimal context (app + model names only)
            return [
                'app'    => $fullSchema['app'] ?? [],
                'models' => array_keys($fullSchema['models'] ?? []),
            ];
        }

        $context = [];

        foreach ($sections as $section) {
            if (array_key_exists($section, $fullSchema)) {
                $context[$section] = $fullSchema[$section];
            }
        }

        return $context;
    }

    /**
     * Find the canonical model key in the schema (case-insensitive).
     *
     * @param  array<string, mixed>  $fullSchema
     */
    private function normalizeModelKey(string $targetModel, array $fullSchema): ?string
    {
        $models = $fullSchema['models'] ?? [];

        // Exact match first
        if (isset($models[$targetModel])) {
            return $targetModel;
        }

        // Case-insensitive match
        $lower = strtolower($targetModel);
        foreach (array_keys($models) as $key) {
            if (strtolower($key) === $lower) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Recursively deep-merge two arrays. Existing values win on scalar conflicts.
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    private function deepMerge(array $base, array $patch): array
    {
        foreach ($patch as $key => $patchValue) {
            if (! array_key_exists($key, $base)) {
                // New key — add it
                $base[$key] = $patchValue;
            } elseif (is_array($patchValue) && is_array($base[$key])) {
                // Both are arrays — recurse
                $base[$key] = $this->deepMerge($base[$key], $patchValue);
            }
            // Scalar conflict: existing (local) value wins — no change
        }

        return $base;
    }
}
