<?php

namespace CodingSunshine\Ensemble\Tests;

use CodingSunshine\Ensemble\Schema\SchemaValidator;
use PHPUnit\Framework\TestCase;

class SchemaValidatorTest extends TestCase
{
    protected SchemaValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new SchemaValidator();
    }

    public function test_valid_minimal_schema_passes(): void
    {
        $schema = [
            'app' => ['name' => 'test-app', 'stack' => 'livewire'],
            'models' => [
                'Post' => [
                    'fields' => [
                        'title' => 'string:200',
                        'body' => 'text',
                    ],
                    'relationships' => [
                        'author' => 'belongsTo:User',
                    ],
                ],
            ],
        ];

        $this->assertTrue($this->validator->validate($schema));
        $this->assertEmpty($this->validator->errors());
    }

    public function test_missing_app_section_is_error(): void
    {
        $this->assertFalse($this->validator->validate(['models' => []]));
        $this->assertNotEmpty($this->validator->errors());
    }

    public function test_invalid_stack_is_error(): void
    {
        $schema = ['app' => ['name' => 'test', 'stack' => 'angular']];
        $this->assertFalse($this->validator->validate($schema));
        $this->assertStringContainsString('Invalid app.stack', $this->validator->errors()[0]);
    }

    public function test_invalid_field_type_is_error(): void
    {
        $schema = [
            'app' => ['name' => 'test', 'stack' => 'livewire'],
            'models' => [
                'Post' => [
                    'fields' => ['title' => 'faketype:200'],
                ],
            ],
        ];

        $this->assertFalse($this->validator->validate($schema));
        $this->assertStringContainsString('unknown type', $this->validator->errors()[0]);
    }

    public function test_valid_field_types_pass(): void
    {
        $schema = [
            'app' => ['name' => 'test', 'stack' => 'livewire'],
            'models' => [
                'Product' => [
                    'fields' => [
                        'name' => 'string:200',
                        'description' => 'text nullable',
                        'price' => 'decimal:10,2',
                        'is_active' => 'boolean default:false',
                        'status' => 'enum:draft,published default:draft',
                        'user_id' => 'id:user',
                        'metadata' => 'json nullable',
                        'published_at' => 'timestamp nullable',
                    ],
                ],
            ],
        ];

        $this->assertTrue($this->validator->validate($schema));
        $this->assertEmpty($this->validator->errors());
    }

    public function test_enum_without_values_is_error(): void
    {
        $schema = [
            'app' => ['name' => 'test', 'stack' => 'livewire'],
            'models' => [
                'Post' => [
                    'fields' => ['status' => 'enum'],
                ],
            ],
        ];

        $this->assertFalse($this->validator->validate($schema));
        $this->assertStringContainsString('enum requires values', $this->validator->errors()[0]);
    }

    public function test_invalid_relationship_format_is_error(): void
    {
        $schema = [
            'app' => ['name' => 'test', 'stack' => 'livewire'],
            'models' => [
                'Post' => [
                    'fields' => ['title' => 'string'],
                    'relationships' => [
                        'author' => 'User',
                    ],
                ],
            ],
        ];

        $this->assertFalse($this->validator->validate($schema));
        $this->assertStringContainsString('must be "type:RelatedModel"', $this->validator->errors()[0]);
    }

    public function test_unknown_relationship_type_is_error(): void
    {
        $schema = [
            'app' => ['name' => 'test', 'stack' => 'livewire'],
            'models' => [
                'Post' => [
                    'fields' => ['title' => 'string'],
                    'relationships' => [
                        'author' => 'linkedTo:User',
                    ],
                ],
            ],
        ];

        $this->assertFalse($this->validator->validate($schema));
        $this->assertStringContainsString('unknown type "linkedTo"', $this->validator->errors()[0]);
    }

    public function test_valid_relationships_pass(): void
    {
        $schema = [
            'app' => ['name' => 'test', 'stack' => 'livewire'],
            'models' => [
                'Post' => [
                    'fields' => ['title' => 'string'],
                    'relationships' => [
                        'author' => 'belongsTo:User',
                        'comments' => 'hasMany:Comment',
                        'tags' => 'belongsToMany:Tag',
                        'image' => 'hasOne:Image',
                        'activities' => 'morphMany:Activity',
                    ],
                ],
            ],
        ];

        $this->assertTrue($this->validator->validate($schema));
        $this->assertEmpty($this->validator->errors());
    }

    public function test_morph_to_relationship_passes(): void
    {
        $schema = [
            'app' => ['name' => 'test', 'stack' => 'livewire'],
            'models' => [
                'Activity' => [
                    'fields' => [
                        'type' => 'string',
                        'activityable_id' => 'integer',
                        'activityable_type' => 'string:200',
                    ],
                    'relationships' => [
                        'activityable' => 'morphTo',
                    ],
                ],
            ],
        ];

        $this->assertTrue($this->validator->validate($schema));
    }

    public function test_auto_fields_produce_warnings(): void
    {
        $schema = [
            'app' => ['name' => 'test', 'stack' => 'livewire'],
            'models' => [
                'Post' => [
                    'fields' => [
                        'id' => 'integer',
                        'title' => 'string',
                        'created_at' => 'timestamp',
                    ],
                ],
            ],
        ];

        $this->assertTrue($this->validator->validate($schema));
        $this->assertNotEmpty($this->validator->warnings());
    }

    public function test_invalid_recipes_format_is_error(): void
    {
        $schema = [
            'app' => ['name' => 'test', 'stack' => 'livewire'],
            'recipes' => 'not-an-array',
        ];

        $this->assertFalse($this->validator->validate($schema));
        $this->assertStringContainsString('must be an array', $this->validator->errors()[0]);
    }

    public function test_recipe_without_name_is_error(): void
    {
        $schema = [
            'app' => ['name' => 'test', 'stack' => 'livewire'],
            'recipes' => [
                ['package' => 'spatie/laravel-permission'],
            ],
        ];

        $this->assertFalse($this->validator->validate($schema));
        $this->assertStringContainsString('missing required "name"', $this->validator->errors()[0]);
    }

    public function test_valid_notifications_pass(): void
    {
        $schema = [
            'app' => ['name' => 'test', 'stack' => 'livewire'],
            'notifications' => [
                'OrderShipped' => [
                    'channels' => ['mail', 'database'],
                    'to' => 'order.user',
                    'subject' => 'Your order {order.number} has shipped',
                ],
            ],
        ];

        $this->assertTrue($this->validator->validate($schema));
    }

    public function test_valid_workflows_pass(): void
    {
        $schema = [
            'app' => ['name' => 'test', 'stack' => 'livewire'],
            'workflows' => [
                'task_lifecycle' => [
                    'model' => 'Task',
                    'field' => 'status',
                    'states' => ['todo', 'in_progress', 'done'],
                    'transitions' => [
                        'start' => ['from' => 'todo', 'to' => 'in_progress'],
                        'complete' => ['from' => 'in_progress', 'to' => 'done'],
                    ],
                ],
            ],
        ];

        $this->assertTrue($this->validator->validate($schema));
    }

    public function test_workflow_missing_model_is_error(): void
    {
        $schema = [
            'app' => ['name' => 'test', 'stack' => 'livewire'],
            'workflows' => [
                'broken' => [
                    'field' => 'status',
                    'states' => ['a', 'b'],
                ],
            ],
        ];

        $this->assertFalse($this->validator->validate($schema));
        $this->assertStringContainsString('missing required "model"', $this->validator->errors()[0]);
    }

    public function test_models_not_object_is_error(): void
    {
        $schema = [
            'app' => ['name' => 'test', 'stack' => 'livewire'],
            'models' => 'not-object',
        ];

        $this->assertFalse($this->validator->validate($schema));
        $this->assertStringContainsString('"models" must be an object', $this->validator->errors()[0]);
    }
}
