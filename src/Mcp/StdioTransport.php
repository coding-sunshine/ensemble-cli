<?php

namespace CodingSunshine\Ensemble\Mcp;

use RuntimeException;

/**
 * JSON-RPC over stdin/stdout (Content-Length header + newline + JSON body per message).
 */
class StdioTransport implements TransportInterface
{
    private const STDIN = 'php://stdin';

    private const STDOUT = 'php://stdout';

    public function readMessage(): ?array
    {
        $stream = fopen(self::STDIN, 'r');
        if ($stream === false) {
            return null;
        }

        $header = '';
        while (true) {
            $line = fgets($stream);
            if ($line === false || $line === '') {
                fclose($stream);
                return null;
            }
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                break;
            }
            $header .= $line."\n";
        }

        if (! preg_match('/Content-Length:\s*(\d+)/i', $header, $m)) {
            fclose($stream);
            return null;
        }

        $length = (int) $m[1];
        $body = '';
        while (strlen($body) < $length) {
            $chunk = fread($stream, $length - strlen($body));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $body .= $chunk;
        }
        fclose($stream);

        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    public function writeMessage(array $message): void
    {
        $body = json_encode($message);
        if ($body === false) {
            throw new RuntimeException('Failed to encode JSON-RPC message');
        }
        $out = fopen(self::STDOUT, 'w');
        if ($out === false) {
            throw new RuntimeException('Cannot write to stdout');
        }
        fwrite($out, 'Content-Length: '.strlen($body)."\r\n\r\n".$body);
        fflush($out);
        fclose($out);
    }
}
