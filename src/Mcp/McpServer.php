<?php

namespace CodingSunshine\Ensemble\Mcp;

use CodingSunshine\Ensemble\Mcp\Tools\AppendModelTool;
use CodingSunshine\Ensemble\Mcp\Tools\AuditProjectTool;
use CodingSunshine\Ensemble\Mcp\Tools\BuildProjectTool;
use CodingSunshine\Ensemble\Mcp\Tools\CreateProjectTool;
use CodingSunshine\Ensemble\Mcp\Tools\GetPackageDetailsTool;
use CodingSunshine\Ensemble\Mcp\Tools\GetSchemaTool;
use CodingSunshine\Ensemble\Mcp\Tools\ListRecipesTool;
use CodingSunshine\Ensemble\Mcp\Tools\McpToolInterface;
use CodingSunshine\Ensemble\Mcp\Tools\SearchPackagesTool;
use CodingSunshine\Ensemble\Mcp\Tools\SnapshotSchemaTool;
use CodingSunshine\Ensemble\Mcp\Tools\UpdateSchemaTool;
use CodingSunshine\Ensemble\Mcp\Tools\ValidateSchemaTool;

/**
 * MCP JSON-RPC server: initialize, tools/list, tools/call.
 */
class McpServer
{
    /** @var list<McpToolInterface> */
    private array $tools;

    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
        $this->tools = [
            new GetSchemaTool,
            new UpdateSchemaTool,
            new ValidateSchemaTool,
            new CreateProjectTool,
            new BuildProjectTool,
            new AppendModelTool,
            new ListRecipesTool,
            new SnapshotSchemaTool,
            new AuditProjectTool,
            new SearchPackagesTool,
            new GetPackageDetailsTool,
        ];
    }

    public function run(): void
    {
        while (true) {
            $message = $this->transport->readMessage();
            if ($message === null) {
                break;
            }

            $id = $message['id'] ?? null;
            $method = $message['method'] ?? '';
            $params = $message['params'] ?? [];

            try {
                $result = $this->handleRequest($method, $params);
                if ($id !== null) {
                    $this->transport->writeMessage([
                        'jsonrpc' => '2.0',
                        'id' => $id,
                        'result' => $result,
                    ]);
                }
            } catch (\Throwable $e) {
                if ($id !== null) {
                    $this->transport->writeMessage([
                        'jsonrpc' => '2.0',
                        'id' => $id,
                        'error' => [
                            'code' => -32603,
                            'message' => $e->getMessage(),
                        ],
                    ]);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function handleRequest(string $method, array $params): array
    {
        if ($method === 'initialize') {
            return [
                'protocolVersion' => '2024-11-05',
                'capabilities' => [
                    'tools' => (object) [],
                ],
                'serverInfo' => [
                    'name' => 'ensemble-cli',
                    'version' => '0.1.0',
                ],
            ];
        }

        if ($method === 'tools/list') {
            return [
                'tools' => array_map(function ($tool) {
                    return [
                        'name' => $tool->name(),
                        'description' => $tool->description(),
                        'inputSchema' => $tool->inputSchema(),
                    ];
                }, $this->tools),
            ];
        }

        if ($method === 'tools/call') {
            $name = $params['name'] ?? '';
            $toolArguments = $params['arguments'] ?? [];
            foreach ($this->tools as $tool) {
                if ($tool->name() === $name) {
                    $result = $tool->execute(is_array($toolArguments) ? $toolArguments : []);

                    return ['content' => [['type' => 'text', 'text' => json_encode($result)]]];
                }
            }
            throw new \InvalidArgumentException("Unknown tool: {$name}");
        }

        throw new \InvalidArgumentException("Unknown method: {$method}");
    }
}
