<?php

namespace CodingSunshine\Ensemble\Mcp;

/**
 * Transport for MCP JSON-RPC messages (stdio now; SSE later).
 */
interface TransportInterface
{
    /**
     * Read one JSON-RPC message. Blocks until one is available or EOF.
     *
     * @return array<string, mixed>|null Decoded request/notification, or null on EOF/close
     */
    public function readMessage(): ?array;

    /**
     * Write one JSON-RPC message (response or notification).
     *
     * @param  array<string, mixed>  $message
     */
    public function writeMessage(array $message): void;
}
