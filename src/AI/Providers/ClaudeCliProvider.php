<?php

namespace CodingSunshine\Ensemble\AI\Providers;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * AI provider that uses the Claude Code CLI (npm i -g @anthropic-ai/claude-code).
 * No API key required — uses the locally installed CLI.
 */
class ClaudeCliProvider implements ProviderContract
{
    protected readonly string $cliPath;

    public function __construct(?string $cliPath = null)
    {
        $this->cliPath = $cliPath ?? $this->findClaudeCli();
    }

    public function complete(string $system, string $user): string
    {
        $prompt = $system !== '' ? $system . "\n\n" . $user : $user;

        $process = new Process([
            $this->cliPath,
            '-p',
            $prompt,
            '--output-format',
            'json',
        ]);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            $err = trim($process->getErrorOutput() ?: $process->getOutput()) ?: $process->getExitCodeText();

            throw new RuntimeException('Claude CLI failed: ' . $err);
        }

        $raw = $process->getOutput();
        $decoded = json_decode($raw, true);

        if (is_array($decoded) && isset($decoded['result'])) {
            return (string) $decoded['result'];
        }

        return trim($raw);
    }

    public function completeStructured(string $system, string $user, array $jsonSchema): array
    {
        $schemaHint = 'Respond with a single valid JSON object only, no markdown or explanation.';
        $combined = $system . "\n\n" . $user . "\n\n" . $schemaHint;

        $process = new Process([
            $this->cliPath,
            '-p',
            $combined,
            '--output-format',
            'json',
        ]);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            $err = trim($process->getErrorOutput() ?: $process->getOutput()) ?: $process->getExitCodeText();

            throw new RuntimeException('Claude CLI failed: ' . $err);
        }

        $raw = $process->getOutput();
        $decoded = json_decode($raw, true);

        if (is_array($decoded) && isset($decoded['result'])) {
            $extracted = json_decode($decoded['result'], true);

            return is_array($extracted) ? $extracted : $this->extractJsonFromText($decoded['result']);
        }

        return $this->extractJsonFromText($raw);
    }

    public function ping(): void
    {
        $process = new Process([$this->cliPath, '--version']);
        $process->setTimeout(5);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                'Claude Code CLI is not installed or not in PATH. Install with: npm install -g @anthropic-ai/claude-code'
            );
        }
    }

    public function estimateTokens(string $system, string $user): int
    {
        return (int) ceil(mb_strlen($system . "\n" . $user) / 4);
    }

    public function name(): string
    {
        return 'Claude CLI (local)';
    }

    private function findClaudeCli(): string
    {
        $finder = new \Symfony\Component\Process\ExecutableFinder();
        $path = $finder->find('claude');

        if ($path !== null) {
            return $path;
        }

        throw new RuntimeException(
            'Claude Code CLI not found in PATH. Install with: npm install -g @anthropic-ai/claude-code'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function extractJsonFromText(string $text): array
    {
        $text = trim($text);

        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $text, $matches)) {
            $text = trim($matches[1]);
        }

        if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
            $decoded = json_decode($matches[0], true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}
