<?php

namespace CodingSunshine\Ensemble\Tests;

use CodingSunshine\Ensemble\AI\SchemaPatcher;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SchemaPatcherTest extends TestCase
{
    protected SchemaPatcher $patcher;

    protected function setUp(): void
    {
        $this->patcher = new SchemaPatcher();
    }

    // -------------------------------------------------------------------------
    // applyPatch / deep-merge
    // -------------------------------------------------------------------------

    public function test_apply_patch_deep_merges_without_overwriting_existing_scalars(): void
    {
        $fullSchema = [
            'app'    => ['name' => 'MyApp', 'stack' => 'livewire'],
            'models' => [
                'User' => ['columns' => ['name' => 'string', 'email' => 'string:unique']],
            ],
        ];

        $patch = [
            'models' => [
                'User'    => ['columns' => ['name' => 'text', 'phone' => 'string:nullable']],
                'Product' => ['columns' => ['title' => 'string']],
            ],
        ];

        $result = $this->patcher->applyPatch($fullSchema, $patch);

        // Existing scalar wins
        $this->assertSame('string', $result['models']['User']['columns']['name']);
        // New key added
        $this->assertSame('string:nullable', $result['models']['User']['columns']['phone']);
        // New model added
        $this->assertArrayHasKey('Product', $result['models']);
        // Unrelated section preserved
        $this->assertSame('MyApp', $result['app']['name']);
    }

    public function test_apply_patch_adds_new_top_level_sections(): void
    {
        $fullSchema = ['app' => ['stack' => 'react'], 'models' => []];
        $patch      = ['pages' => ['Dashboard' => 'dashboard'], 'roles' => ['admin' => []]];

        $result = $this->patcher->applyPatch($fullSchema, $patch);

        $this->assertArrayHasKey('pages', $result);
        $this->assertArrayHasKey('roles', $result);
        $this->assertSame(['stack' => 'react'], $result['app']);
    }

    public function test_apply_patch_does_not_overwrite_existing_app_keys(): void
    {
        $fullSchema = ['app' => ['name' => 'Local', 'stack' => 'vue'], 'models' => []];
        $patch      = ['app' => ['name' => 'Remote']];

        $result = $this->patcher->applyPatch($fullSchema, $patch);

        $this->assertSame('Local', $result['app']['name']);
        $this->assertSame('vue', $result['app']['stack']);
    }

    // -------------------------------------------------------------------------
    // diff
    // -------------------------------------------------------------------------

    public function test_diff_detects_added_modified_and_removed_keys(): void
    {
        $before = [
            'models'  => ['User' => [], 'Post' => []],
            'recipes' => ['spatie/permission'],
        ];

        $after = [
            'models'      => ['User' => [], 'Comment' => []],
            'controllers' => ['PostController' => []],
        ];

        $result = $this->patcher->diff($before, $after);

        $this->assertArrayHasKey('controllers', $result['added']);
        $this->assertArrayHasKey('recipes', $result['removed']);
        $this->assertArrayHasKey('models', $result['modified']);
    }

    public function test_diff_returns_empty_arrays_for_identical_schemas(): void
    {
        $schema = ['app' => ['stack' => 'vue'], 'models' => ['User' => []]];
        $result = $this->patcher->diff($schema, $schema);

        $this->assertEmpty($result['added']);
        $this->assertEmpty($result['modified']);
        $this->assertEmpty($result['removed']);
    }

    // -------------------------------------------------------------------------
    // detectTargetSections
    // -------------------------------------------------------------------------

    public function test_detect_target_sections_identifies_models_keyword(): void
    {
        $sections = $this->patcher->detectTargetSections('add a status field to the Order model');

        $this->assertContains('models', $sections);
    }

    public function test_detect_target_sections_identifies_multiple_sections(): void
    {
        $sections = $this->patcher->detectTargetSections('add a workflow for orders and a dashboard for metrics');

        $this->assertContains('workflows', $sections);
        $this->assertContains('dashboards', $sections);
    }

    public function test_detect_target_sections_returns_null_when_no_keywords_match(): void
    {
        $sections = $this->patcher->detectTargetSections('make everything faster and better');

        $this->assertNull($sections);
    }

    public function test_detect_target_sections_maps_permission_to_roles(): void
    {
        $sections = $this->patcher->detectTargetSections('add admin permission to users');

        $this->assertContains('roles', $sections);
    }

    public function test_detect_target_sections_does_not_duplicate_sections(): void
    {
        // "role" and "roles" both map to "roles"
        $sections = $this->patcher->detectTargetSections('update the roles and role assignments');

        $this->assertSame(1, count(array_filter($sections, fn ($s) => $s === 'roles')));
    }

    // -------------------------------------------------------------------------
    // buildDeltaPrompt
    // -------------------------------------------------------------------------

    public function test_build_delta_prompt_targets_only_specified_model(): void
    {
        $fullSchema = [
            'app'    => ['stack' => 'livewire'],
            'models' => [
                'User'    => ['columns' => ['name' => 'string']],
                'Product' => ['columns' => ['title' => 'string']],
                'Order'   => ['columns' => ['total' => 'decimal:8,2']],
            ],
        ];

        $prompt = $this->patcher->buildDeltaPrompt($fullSchema, 'add a status field', 'Order');

        $this->assertStringContainsString('Order', $prompt);
        $this->assertStringNotContainsString('Product', $prompt);
        $this->assertStringNotContainsString('"User"', $prompt);
    }

    public function test_build_delta_prompt_sends_minimal_context_without_target_model(): void
    {
        $fullSchema = [
            'app'    => ['stack' => 'vue'],
            'models' => ['User' => [], 'Post' => []],
            'pages'  => ['Home' => 'home'],
        ];

        $prompt = $this->patcher->buildDeltaPrompt($fullSchema, 'update the pages section');

        $this->assertStringContainsString('pages', $prompt);
        $this->assertStringContainsString('Change requested', $prompt);
    }

    public function test_build_delta_prompt_includes_request_text(): void
    {
        $fullSchema = ['app' => [], 'models' => []];
        $request    = 'add invoice PDF generation';

        $prompt = $this->patcher->buildDeltaPrompt($fullSchema, $request);

        $this->assertStringContainsString($request, $prompt);
    }

    // -------------------------------------------------------------------------
    // Circular import detection
    // -------------------------------------------------------------------------

    public function test_detect_circular_imports_passes_for_schema_with_no_imports(): void
    {
        $schema = ['app' => [], 'models' => []];

        // Must not throw
        $this->patcher->detectCircularImports($schema);
        $this->assertTrue(true);
    }

    public function test_detect_circular_imports_passes_for_linear_chain(): void
    {
        $registry = [
            'base.json'    => ['imports' => []],
            'feature.json' => ['imports' => ['base.json']],
        ];

        $schema = ['imports' => ['feature.json']];

        // Must not throw
        $this->patcher->detectCircularImports($schema, $registry, [], 'root');
        $this->assertTrue(true);
    }

    public function test_detect_circular_imports_throws_on_direct_cycle(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Circular import detected/');

        $registry = [
            'a.json' => ['imports' => ['b.json']],
            'b.json' => ['imports' => ['a.json']],
        ];

        $schema = ['imports' => ['a.json']];

        $this->patcher->detectCircularImports($schema, $registry, [], 'root');
    }

    public function test_detect_circular_imports_exception_includes_cycle_path(): void
    {
        $registry = [
            'a.json' => ['imports' => ['b.json']],
            'b.json' => ['imports' => ['a.json']],
        ];

        $schema = ['imports' => ['a.json']];

        try {
            $this->patcher->detectCircularImports($schema, $registry, [], 'root');
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('a.json', $e->getMessage());
            $this->assertStringContainsString('b.json', $e->getMessage());
        }
    }
}
