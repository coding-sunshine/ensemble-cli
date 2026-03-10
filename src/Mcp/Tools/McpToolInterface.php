<?php

namespace CodingSunshine\Ensemble\Mcp\Tools;

/**
 * MCP tool that can be listed and invoked.
 */
interface McpToolInterface
{
    public function name(): string;

    public function description(): string;

    /**
     * JSON Schema for the tool's input (key "properties" etc.).
     *
     * @return array<string, mixed>
     */
    public function inputSchema(): array;

    /**
     * Execute the tool with the given arguments (from the JSON-RPC request).
     *
     * @param  array<string, mixed>  $arguments
     * @return mixed Result to return to the client (must be JSON-serializable)
     */
    public function execute(array $arguments): mixed;
}
