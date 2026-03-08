<?php

namespace CodingSunshine\Ensemble\AI;

class SchemaJsonSchema
{
    /**
     * Return the full JSON Schema definition for a complete ensemble schema.
     *
     * @return array<string, mixed>
     */
    public static function definition(): array
    {
        return [
            'type' => 'object',
            'name' => 'ensemble_schema',
            'description' => 'A complete Laravel Ensemble application schema',
            'properties' => [
                'version' => ['type' => 'integer'],
                'app' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'stack' => ['type' => 'string', 'enum' => ['livewire', 'react', 'vue', 'svelte']],
                        'ui' => ['type' => 'string'],
                    ],
                    'additionalProperties' => true,
                ],
                'models' => [
                    'type' => 'object',
                    'additionalProperties' => [
                        'type' => 'object',
                        'properties' => [
                            'columns' => ['type' => 'object', 'additionalProperties' => ['type' => 'string']],
                            'relationships' => ['type' => 'object', 'additionalProperties' => true],
                            'indexes' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'softDeletes' => ['type' => 'boolean'],
                            'timestamps' => ['type' => 'boolean'],
                        ],
                        'additionalProperties' => true,
                    ],
                ],
                'controllers' => [
                    'type' => 'object',
                    'additionalProperties' => [
                        'type' => 'object',
                        'additionalProperties' => true,
                    ],
                ],
                'pages' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                ],
                'notifications' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                ],
                'workflows' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                ],
                'dashboards' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                ],
                'roles' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                ],
                'services' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                ],
                'schedules' => [
                    'type' => 'array',
                    'items' => ['type' => 'object', 'additionalProperties' => true],
                ],
                'broadcasts' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                ],
                'imports' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'recipes' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'package' => ['type' => 'string'],
                        ],
                        'additionalProperties' => true,
                    ],
                ],
            ],
            'required' => ['app', 'models'],
            'additionalProperties' => true,
        ];
    }

    /**
     * Return the JSON Schema definition for a delta schema patch (partial update).
     *
     * @return array<string, mixed>
     */
    public static function patchDefinition(): array
    {
        return [
            'type' => 'object',
            'name' => 'ensemble_schema_patch',
            'description' => 'A partial delta patch to merge into an existing Laravel Ensemble schema',
            'properties' => [
                'models' => [
                    'type' => 'object',
                    'additionalProperties' => [
                        'type' => 'object',
                        'additionalProperties' => true,
                    ],
                ],
                'controllers' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                ],
                'pages' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                ],
                'notifications' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                ],
                'workflows' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                ],
                'dashboards' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                ],
                'roles' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                ],
                'services' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                ],
                'schedules' => [
                    'type' => 'array',
                    'items' => ['type' => 'object', 'additionalProperties' => true],
                ],
                'broadcasts' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                ],
                'imports' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'recipes' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'package' => ['type' => 'string'],
                        ],
                        'additionalProperties' => true,
                    ],
                ],
            ],
            'additionalProperties' => true,
        ];
    }
}
