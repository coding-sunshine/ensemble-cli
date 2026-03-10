<?php

namespace CodingSunshine\Ensemble\Console;

use CodingSunshine\Ensemble\Mcp\McpServer;
use CodingSunshine\Ensemble\Mcp\StdioTransport;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class McpCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('mcp')->setDescription('Run the MCP server (JSON-RPC over stdio)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $transport = new StdioTransport;
        $server = new McpServer($transport);
        $server->run();

        return self::SUCCESS;
    }
}
