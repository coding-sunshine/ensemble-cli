<?php

namespace CodingSunshine\Ensemble\Tests;

use CodingSunshine\Ensemble\Console\McpCommand;
use CodingSunshine\Ensemble\Mcp\McpServer;
use CodingSunshine\Ensemble\Mcp\Tools\GetSchemaTool;
use CodingSunshine\Ensemble\Mcp\Tools\ListRecipesTool;
use CodingSunshine\Ensemble\Mcp\Tools\ValidateSchemaTool;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @see \CodingSunshine\Ensemble\Console\McpCommand
 * @see \CodingSunshine\Ensemble\Mcp\McpServer
 */
class McpCommandTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────────────────
    // McpServer unit tests
    // ─────────────────────────────────────────────────────────────────────────

    public function test_mcp_server_returns_initialize_response(): void
    {
        $server = new McpServer([]);
        $response = $server->handle([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [],
        ]);

        $this->assertSame('2.0', $response['jsonrpc']);
        $this->assertSame(1, $response['id']);
        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('protocolVersion', $response['result']);
    }

    public function test_mcp_server_lists_tools(): void
    {
        $server = new McpServer([
            new GetSchemaTool(),
            new ListRecipesTool(),
        ]);

        $response = $server->handle([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
            'params' => [],
        ]);

        $tools = $response['result']['tools'] ?? [];
        $names = array_column($tools, 'name');

        $this->assertContains('get_schema', $names);
        $this->assertContains('list_recipes', $names);
    }

    public function test_mcp_server_returns_error_for_unknown_method(): void
    {
        $server = new McpServer([]);
        $response = $server->handle([
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'unknown/method',
            'params' => [],
        ]);

        $this->assertArrayHasKey('error', $response);
        $this->assertSame(3, $response['id']);
    }

    public function test_mcp_server_returns_null_for_notification(): void
    {
        $server = new McpServer([]);
        // No 'id' means a notification — server should return null and not crash
        $result = $server->handle([
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
            'params' => [],
        ]);

        $this->assertNull($result);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ValidateSchemaTool
    // ─────────────────────────────────────────────────────────────────────────

    public function test_validate_schema_tool_with_valid_schema(): void
    {
        $tool = new ValidateSchemaTool();

        $tmpPath = sys_get_temp_dir() . '/ensemble-mcp-test-' . uniqid() . '.json';
        file_put_contents($tmpPath, json_encode([
            'version' => 1,
            'app' => ['name' => 'test', 'stack' => 'blade'],
            'models' => ['Post' => ['fields' => ['title' => 'string']]],
        ]));

        try {
            $result = $tool->execute(['path' => $tmpPath]);
            $this->assertTrue($result['valid']);
        } finally {
            unlink($tmpPath);
        }
    }

    public function test_validate_schema_tool_with_missing_file(): void
    {
        $tool = new ValidateSchemaTool();

        $this->expectException(\InvalidArgumentException::class);
        $tool->execute(['path' => '/nonexistent/ensemble.json']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ListRecipesTool
    // ─────────────────────────────────────────────────────────────────────────

    public function test_list_recipes_tool_returns_array(): void
    {
        $tool = new ListRecipesTool();
        $result = $tool->execute([]);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('name', $result[0]);
        $this->assertArrayHasKey('package', $result[0]);
    }
}
