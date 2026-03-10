<?php

namespace CodingSunshine\Ensemble\AI\Providers;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * AI provider that uses the Google Gemini CLI (npm i -g @google/gemini-cli).
 * Free tier: 1,000 requests/day with a personal Google account.
 */
class GeminiCliProvider implements ProviderContract
{
    protected readonly string $cliPath;

    public function __construct(?string $cliPath = null)
    {
        $this->cliPath = $cliPath ?? $this->findGeminiCli();
    }

    public function complete(string $system, string $user): string
    {
        $prompt = $system !== '' ? $system . "\n\n" . $user : $user;

        $process = new Process([
            $this->cliPath,
            '-p',
            $prompt,
        ]);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            $err = trim($process->getErrorOutput() ?: $process->getOutput()) ?: $process->getExitCodeText();

            throw new RuntimeException('Gemini CLI failed: ' . $err);
        }

        return trim($process->getOutput());
    }

    public function completeStructured(string $system, string $user, array $jsonSchema): array
    {
        $schemaHint = 'Respond with a single valid JSON object only, no markdown or explanation.';
        $combined = $system . "\n\n" . $user . "\n\n" . $schemaHint;

        $process = new Process([
            $this->cliPath,
            '-p',
            $combined,
        ]);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            $err = trim($process->getErrorOutput() ?: $process->getOutput()) ?: $process->getExitCodeText();

            throw new RuntimeException('Gemini CLI failed: ' . $err);
        }

        return $this->extractJsonFromText($process->getOutput());
    }

    public function ping(): void
    {
        $process = new Process([$this->cliPath, '--version']);
        $process->setTimeout(5);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                'Gemini CLI is not installed or not in PATH. Install with: npm install -g @google/gemini-cli'
            );
        }
    }

    public function estimateTokens(string $system, string $user): int
    {
        return (int) ceil(mb_strlen($system . "\n" . $user) / 4);
    }

    public function name(): string
    {
        return 'Gemini CLI (local)';
    }

    private function findGeminiCli(): string
    {
        $finder = new \Symfony\Component\Process\ExecutableFinder();
        $path = $finder->find('gemini');

        if ($path !== null) {
            return $path;
        }

        throw new RuntimeException(
            'Gemini CLI not found in PATH. Install with: npm install -g @google/gemini-cli'
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
