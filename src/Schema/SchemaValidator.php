<?php

namespace CodingSunshine\Ensemble\Schema;

class SchemaValidator
{
    protected const VALID_FIELD_TYPES = [
        'string', 'text', 'longText', 'mediumText',
        'integer', 'bigInteger', 'smallInteger', 'tinyInteger', 'unsignedInteger', 'unsignedBigInteger',
        'decimal', 'float', 'double',
        'boolean',
        'date', 'datetime', 'timestamp', 'time', 'year',
        'json', 'jsonb',
        'uuid', 'ulid',
        'binary', 'char',
        'id', 'enum',
        'ipAddress', 'macAddress',
    ];

    protected const VALID_RELATIONSHIP_TYPES = [
        'hasOne', 'hasMany', 'belongsTo', 'belongsToMany',
        'morphOne', 'morphMany', 'morphTo', 'morphToMany', 'morphedByMany',
    ];

    protected const VALID_FIELD_MODIFIERS = [
        'nullable', 'unique', 'index', 'unsigned', 'default',
    ];

    protected const VALID_LAYOUTS = [
        'sidebar', 'default', 'full-width',
    ];

    protected const VALID_CONTROLLER_RESOURCE_TYPES = [
        'web', 'api',
    ];

    protected const VALID_NOTIFICATION_CHANNELS = [
        'mail', 'database', 'broadcast', 'slack', 'vonage', 'nexmo',
    ];

    /**
     * @var array<int, string>
     */
    protected array $errors = [];

    /**
     * @var array<int, string>
     */
    protected array $warnings = [];

    /**
     * Validate a schema array and return whether it passed.
     *
     * @param  array<string, mixed>  $schema
     */
    public function validate(array $schema): bool
    {
        $this->errors = [];
        $this->warnings = [];

        $this->validateApp($schema);
        $this->validateModels($schema);
        $this->validateControllers($schema);
        $this->validatePages($schema);
        $this->validateRecipes($schema);
        $this->validateNotifications($schema);
        $this->validateWorkflows($schema);

        return empty($this->errors);
    }

    /**
     * @return array<int, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<int, string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    protected function validateApp(array $schema): void
    {
        if (! isset($schema['app'])) {
            $this->errors[] = 'Missing required "app" section.';

            return;
        }

        if (! is_array($schema['app'])) {
            $this->errors[] = '"app" must be an object.';

            return;
        }

        if (! isset($schema['app']['name']) || ! is_string($schema['app']['name'])) {
            $this->warnings[] = 'Missing "app.name" — a kebab-case application name is recommended.';
        }

        if (isset($schema['app']['stack'])) {
            $validStacks = ['livewire', 'react', 'vue', 'svelte'];

            if (! in_array($schema['app']['stack'], $validStacks)) {
                $this->errors[] = "Invalid app.stack \"{$schema['app']['stack']}\". Valid: ".implode(', ', $validStacks);
            }
        }
    }

    protected function validateModels(array $schema): void
    {
        if (! isset($schema['models'])) {
            return;
        }

        if (! is_array($schema['models'])) {
            $this->errors[] = '"models" must be an object.';

            return;
        }

        foreach ($schema['models'] as $modelName => $definition) {
            if (! is_array($definition)) {
                $this->errors[] = "Model \"{$modelName}\" must be an object.";

                continue;
            }

            if (! preg_match('/^[A-Z][a-zA-Z0-9]*$/', $modelName)) {
                $this->warnings[] = "Model name \"{$modelName}\" should be PascalCase.";
            }

            $this->validateModelFields($modelName, $definition);
            $this->validateModelRelationships($modelName, $definition);
        }
    }

    protected function validateModelFields(string $modelName, array $definition): void
    {
        if (! isset($definition['fields'])) {
            $this->warnings[] = "Model \"{$modelName}\" has no fields defined.";

            return;
        }

        if (! is_array($definition['fields'])) {
            $this->errors[] = "Model \"{$modelName}\".fields must be an object.";

            return;
        }

        $autoFields = ['id', 'created_at', 'updated_at', 'deleted_at'];

        foreach ($definition['fields'] as $fieldName => $fieldDef) {
            if (in_array($fieldName, $autoFields)) {
                $this->warnings[] = "Model \"{$modelName}\".fields.\"{$fieldName}\" is automatic and should not be defined.";
            }

            if (! is_string($fieldDef)) {
                $this->errors[] = "Model \"{$modelName}\".fields.\"{$fieldName}\" — definition must be a string.";

                continue;
            }

            $this->validateFieldDefinition($modelName, $fieldName, $fieldDef);
        }
    }

    protected function validateFieldDefinition(string $modelName, string $fieldName, string $definition): void
    {
        $parts = preg_split('/\s+/', trim($definition));
        $typePart = $parts[0];

        $baseType = explode(':', $typePart)[0];

        if (! in_array($baseType, self::VALID_FIELD_TYPES)) {
            $this->errors[] = "Model \"{$modelName}\".fields.\"{$fieldName}\" — unknown type \"{$baseType}\". Valid types: ".implode(', ', array_slice(self::VALID_FIELD_TYPES, 0, 10)).'...';
        }

        if ($baseType === 'enum') {
            $colonParts = explode(':', $typePart, 2);

            if (! isset($colonParts[1]) || empty($colonParts[1])) {
                $this->errors[] = "Model \"{$modelName}\".fields.\"{$fieldName}\" — enum requires values (e.g. \"enum:draft,published\").";
            }
        }

        if ($baseType === 'decimal') {
            $colonParts = explode(':', $typePart, 2);

            if (isset($colonParts[1]) && ! preg_match('/^\d+,\d+$/', $colonParts[1])) {
                $this->warnings[] = "Model \"{$modelName}\".fields.\"{$fieldName}\" — decimal precision should be \"decimal:precision,scale\" (e.g. \"decimal:10,2\").";
            }
        }

        $modifiers = array_slice($parts, 1);

        foreach ($modifiers as $modifier) {
            $modBase = explode(':', $modifier)[0];

            if (! in_array($modBase, self::VALID_FIELD_MODIFIERS)) {
                $this->warnings[] = "Model \"{$modelName}\".fields.\"{$fieldName}\" — unknown modifier \"{$modBase}\".";
            }
        }
    }

    protected function validateModelRelationships(string $modelName, array $definition): void
    {
        if (! isset($definition['relationships'])) {
            return;
        }

        if (! is_array($definition['relationships'])) {
            $this->errors[] = "Model \"{$modelName}\".relationships must be an object.";

            return;
        }

        foreach ($definition['relationships'] as $relName => $relDef) {
            if (! is_string($relDef)) {
                $this->errors[] = "Model \"{$modelName}\".relationships.\"{$relName}\" — definition must be a string.";

                continue;
            }

            if ($relDef === 'morphTo') {
                continue;
            }

            if (! str_contains($relDef, ':')) {
                $this->errors[] = "Model \"{$modelName}\".relationships.\"{$relName}\" — must be \"type:RelatedModel\" (e.g. \"hasMany:Comment\"), got \"{$relDef}\".";

                continue;
            }

            $relType = explode(':', $relDef)[0];

            if (! in_array($relType, self::VALID_RELATIONSHIP_TYPES)) {
                $this->errors[] = "Model \"{$modelName}\".relationships.\"{$relName}\" — unknown type \"{$relType}\". Valid: ".implode(', ', self::VALID_RELATIONSHIP_TYPES);
            }
        }
    }

    protected function validateControllers(array $schema): void
    {
        if (! isset($schema['controllers'])) {
            return;
        }

        if (! is_array($schema['controllers'])) {
            $this->errors[] = '"controllers" must be an object.';

            return;
        }

        foreach ($schema['controllers'] as $name => $definition) {
            if (! is_array($definition)) {
                $this->errors[] = "Controller \"{$name}\" must be an object.";

                continue;
            }

            if (isset($definition['resource']) && ! in_array($definition['resource'], self::VALID_CONTROLLER_RESOURCE_TYPES)) {
                $this->warnings[] = "Controller \"{$name}\" has unknown resource type \"{$definition['resource']}\". Expected: web, api.";
            }
        }
    }

    protected function validatePages(array $schema): void
    {
        if (! isset($schema['pages'])) {
            return;
        }

        if (! is_array($schema['pages'])) {
            $this->errors[] = '"pages" must be an object.';

            return;
        }

        foreach ($schema['pages'] as $routeName => $definition) {
            if (! is_array($definition)) {
                $this->errors[] = "Page \"{$routeName}\" must be an object.";

                continue;
            }

            if (isset($definition['layout']) && ! in_array($definition['layout'], self::VALID_LAYOUTS)) {
                $this->warnings[] = "Page \"{$routeName}\" has non-standard layout \"{$definition['layout']}\".";
            }
        }
    }

    protected function validateRecipes(array $schema): void
    {
        if (! isset($schema['recipes'])) {
            return;
        }

        if (! is_array($schema['recipes'])) {
            $this->errors[] = '"recipes" must be an array.';

            return;
        }

        foreach ($schema['recipes'] as $index => $recipe) {
            if (! is_array($recipe)) {
                $this->errors[] = "Recipe at index {$index} must be an object.";

                continue;
            }

            if (! isset($recipe['name'])) {
                $this->errors[] = "Recipe at index {$index} is missing required \"name\" field.";
            }
        }
    }

    protected function validateNotifications(array $schema): void
    {
        if (! isset($schema['notifications'])) {
            return;
        }

        if (! is_array($schema['notifications'])) {
            $this->errors[] = '"notifications" must be an object.';

            return;
        }

        foreach ($schema['notifications'] as $name => $definition) {
            if (! is_array($definition)) {
                $this->errors[] = "Notification \"{$name}\" must be an object.";

                continue;
            }

            if (isset($definition['channels'])) {
                foreach ($definition['channels'] as $channel) {
                    if (! in_array($channel, self::VALID_NOTIFICATION_CHANNELS)) {
                        $this->warnings[] = "Notification \"{$name}\" has unknown channel \"{$channel}\".";
                    }
                }
            }
        }
    }

    protected function validateWorkflows(array $schema): void
    {
        if (! isset($schema['workflows'])) {
            return;
        }

        if (! is_array($schema['workflows'])) {
            $this->errors[] = '"workflows" must be an object.';

            return;
        }

        foreach ($schema['workflows'] as $name => $definition) {
            if (! is_array($definition)) {
                $this->errors[] = "Workflow \"{$name}\" must be an object.";

                continue;
            }

            if (! isset($definition['model'])) {
                $this->errors[] = "Workflow \"{$name}\" is missing required \"model\" field.";
            }

            if (! isset($definition['field'])) {
                $this->errors[] = "Workflow \"{$name}\" is missing required \"field\" field.";
            }

            if (! isset($definition['states']) || ! is_array($definition['states'])) {
                $this->errors[] = "Workflow \"{$name}\" must have a \"states\" array.";
            }

            if (isset($definition['transitions'])) {
                foreach ($definition['transitions'] as $transName => $trans) {
                    if (! isset($trans['from']) || ! isset($trans['to'])) {
                        $this->errors[] = "Workflow \"{$name}\".transitions.\"{$transName}\" must have \"from\" and \"to\" fields.";
                    }
                }
            }
        }
    }
}
