<?php

namespace CodingSunshine\Ensemble\Config;

class ConfigStore
{
    protected readonly string $configPath;

    protected array $data;

    public function __construct(?string $configPath = null)
    {
        $this->configPath = $configPath ?? $this->defaultConfigPath();
        $this->data = $this->load();
    }

    /**
     * Get a config value by dot-notated key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $current = $this->data;

        foreach ($segments as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return $default;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * Set a config value by dot-notated key and persist to disk.
     */
    public function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $current = &$this->data;

        foreach (array_slice($segments, 0, -1) as $segment) {
            if (! isset($current[$segment]) || ! is_array($current[$segment])) {
                $current[$segment] = [];
            }

            $current = &$current[$segment];
        }

        $current[end($segments)] = $value;

        $this->persist();
    }

    /**
     * Check if a config key exists and has a non-null value.
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Detect the first available local AI provider (no API key required).
     * Order: claude-cli, gemini-cli, ollama, lmstudio.
     */
    public function detectLocalProvider(): ?string
    {
        $finder = new \Symfony\Component\Process\ExecutableFinder();

        if ($finder->find('claude') !== null) {
            $p = new \Symfony\Component\Process\Process(['claude', '--version']);
            $p->setTimeout(3);
            $p->run();
            if ($p->isSuccessful()) {
                return 'claude-cli';
            }
        }

        if ($finder->find('gemini') !== null) {
            $p = new \Symfony\Component\Process\Process(['gemini', '--version']);
            $p->setTimeout(3);
            $p->run();
            if ($p->isSuccessful()) {
                return 'gemini-cli';
            }
        }

        $p = new \Symfony\Component\Process\Process(['ollama', 'list']);
        $p->setTimeout(3);
        $p->run();
        if ($p->isSuccessful()) {
            return 'ollama';
        }

        $client = new \GuzzleHttp\Client(['timeout' => 2, 'connect_timeout' => 1]);
        try {
            $client->get('http://localhost:1234/v1/models');
            return 'lmstudio';
        } catch (\Throwable) {
            // ignore
        }

        return null;
    }

    /**
     * Get the full config array.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Load config from disk, or return empty array if file doesn't exist.
     *
     * @return array<string, mixed>
     */
    protected function load(): array
    {
        if (! file_exists($this->configPath)) {
            return [];
        }

        $contents = file_get_contents($this->configPath);
        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Write current config state to disk.
     */
    protected function persist(): void
    {
        $directory = dirname($this->configPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        file_put_contents(
            $this->configPath,
            json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n"
        );

        chmod($this->configPath, 0600);
    }

    protected function defaultConfigPath(): string
    {
        $home = match (true) {
            isset($_SERVER['HOME']) => $_SERVER['HOME'],
            isset($_SERVER['USERPROFILE']) => $_SERVER['USERPROFILE'],
            default => getenv('HOME') ?: getenv('USERPROFILE') ?: '~',
        };

        return $home.'/.ensemble/config.json';
    }
}
