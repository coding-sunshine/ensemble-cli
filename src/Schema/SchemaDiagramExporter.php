<?php

namespace CodingSunshine\Ensemble\Schema;

/**
 * Export ensemble schema as Mermaid ER diagram or schema-graph JSON.
 *
 * This class is duplicated in the ensemble Laravel package (CodingSunshine\Ensemble\Studio\DiagramExporter).
 * The ensemble package version is instantiable; this version uses static methods.
 * When changing Mermaid output or relationship handling, sync both files.
 *
 * @see ensemble/src/Studio/DiagramExporter.php — Laravel package counterpart
 */
class SchemaDiagramExporter
{
    /**
     * Build Mermaid ER diagram from schema array.
     *
     * @param  array<string, mixed>  $schema
     */
    public static function toMermaid(array $schema): string
    {
        $models = $schema['models'] ?? [];
        if (empty($models)) {
            return "erDiagram\n";
        }

        $lines = ['erDiagram'];
        $seenRelationships = [];

        foreach ($models as $modelName => $definition) {
            $fields = $definition['fields'] ?? [];
            $keyFields = self::keyFieldsForMermaid($modelName, $fields, $definition['relationships'] ?? []);
            $lines[] = "    {$modelName} {";
            foreach ($keyFields as $field) {
                $lines[] = "        {$field}";
            }
            $lines[] = '    }';
        }

        foreach ($models as $modelName => $definition) {
            $relationships = $definition['relationships'] ?? [];
            foreach ($relationships as $relName => $relSpec) {
                $parsed = self::parseRelationshipSpec($relSpec);
                if ($parsed === null) {
                    continue;
                }
                [$type, $target] = $parsed;
                $edge = self::mermaidRelationshipLine($modelName, $target, $type, $relName);
                if ($edge !== null && ! isset($seenRelationships[$edge])) {
                    $seenRelationships[$edge] = true;
                    $lines[] = '    '.$edge;
                }
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * Build schema-graph structure: nodes and edges.
     *
     * @param  array<string, mixed>  $schema
     * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    public static function toSchemaGraph(array $schema): array
    {
        $models = $schema['models'] ?? [];
        $nodes = [];
        $edges = [];

        foreach ($models as $modelName => $definition) {
            $fields = $definition['fields'] ?? [];
            $fieldList = array_keys($fields);
            $nodes[] = [
                'id' => $modelName,
                'label' => $modelName,
                'type' => 'model',
                'fields' => $fieldList,
            ];

            $relationships = $definition['relationships'] ?? [];
            foreach ($relationships as $relName => $relSpec) {
                $parsed = self::parseRelationshipSpec($relSpec);
                if ($parsed === null) {
                    continue;
                }
                [$type, $target] = $parsed;
                $edges[] = [
                    'from' => $modelName,
                    'to' => $target,
                    'relationship' => $type,
                    'label' => $relName,
                ];
            }
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    /**
     * @param  array<string, string>  $fields
     * @param  array<string, string>  $relationships
     * @return array<int, string>
     */
    private static function keyFieldsForMermaid(string $modelName, array $fields, array $relationships): array
    {
        $out = [];
        if (isset($fields['id'])) {
            $out[] = 'string id PK';
        }
        foreach (array_keys($fields) as $name) {
            if ($name === 'id') {
                continue;
            }
            if (str_ends_with($name, '_id')) {
                $out[] = 'string '.$name.' FK';
            }
        }
        $rest = array_diff_key($fields, array_flip(['id']), array_flip(array_filter(array_keys($fields), fn ($k) => str_ends_with((string) $k, '_id'))));
        $i = 0;
        foreach (array_keys($rest) as $name) {
            if ($i >= 3) {
                break;
            }
            $out[] = 'string '.$name;
            $i++;
        }
        if (empty($out)) {
            $out[] = 'string id PK';
        }

        return $out;
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private static function parseRelationshipSpec(string $spec): ?array
    {
        if (str_contains($spec, ':')) {
            $parts = explode(':', $spec, 2);
            $type = trim($parts[0]);
            $target = trim($parts[1] ?? '');
            if ($target !== '') {
                return [$type, $target];
            }
        }

        return null;
    }

    private static function mermaidRelationshipLine(string $from, string $to, string $type, string $label): ?string
    {
        $cardinality = [
            // Standard Eloquent
            'hasMany'         => '||--o{',
            'belongsTo'       => '}o--||',
            'hasOne'          => '||--o|',
            'belongsToMany'   => '}o--o{',
            // Through
            'hasManyThrough'  => '||--o{',
            'hasOneThrough'   => '||--o|',
            // Polymorphic
            'morphMany'       => '||--o{',
            'morphOne'        => '||--o|',
            'morphTo'         => '}o--||',
            'morphToMany'     => '}o--o{',
            'morphedByMany'   => '}o--o{',
        ];
        $symbol = $cardinality[$type] ?? null;
        if ($symbol === null) {
            return null;
        }

        return "{$from} {$symbol} {$to} : \"{$label}\"";
    }
}
