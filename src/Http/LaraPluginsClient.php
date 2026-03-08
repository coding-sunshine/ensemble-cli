<?php

namespace CodingSunshine\Ensemble\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

class LaraPluginsClient
{
    private const BASE_URL = 'https://laraplugins.io/api';

    private const CACHE_TTL = 3600; // 1 hour in seconds

    private Client $http;

    private string $cacheDir;

    public function __construct(?Client $http = null, ?string $cacheDir = null)
    {
        $this->http = $http ?? new Client(['timeout' => 10]);
        $this->cacheDir = $cacheDir ?? $this->defaultCacheDir();
    }

    /**
     * Search for packages on laraplugins.io.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, array $filters = []): array
    {
        $params = array_merge(['q' => $query], $filters);
        $cacheKey = hash('sha256', serialize(['search', $params]));

        $cached = $this->loadCache($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = $this->http->get(self::BASE_URL.'/search', ['query' => $params]);
            $data = json_decode((string) $response->getBody(), true) ?? [];
            $results = $data['data'] ?? $data;

            $this->saveCache($cacheKey, $results);

            return $results;
        } catch (GuzzleException $e) {
            return [];
        }
    }

    /**
     * Get details for a specific package.
     *
     * @return array<string, mixed>|null
     */
    public function getDetails(string $package): ?array
    {
        $cacheKey = hash('sha256', serialize(['details', $package]));

        $cached = $this->loadCache($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        try {
            $encoded = urlencode($package);
            $response = $this->http->get(self::BASE_URL."/packages/{$encoded}");
            $data = json_decode((string) $response->getBody(), true);

            if (! is_array($data)) {
                return null;
            }

            $result = $data['data'] ?? $data;

            $this->saveCache($cacheKey, $result);

            return $result;
        } catch (GuzzleException $e) {
            return null;
        }
    }

    /**
     * Format a health score string into a colored badge label.
     *
     * Scores: healthy → 🟢 Healthy, medium → 🟡 Medium, unhealthy → 🔴 Unhealthy
     */
    public function formatHealthScore(string $score): string
    {
        return match (strtolower($score)) {
            'healthy', 'good', 'high' => '🟢 Healthy',
            'medium', 'moderate', 'fair' => '🟡 Medium',
            'unhealthy', 'poor', 'low', 'bad' => '🔴 Unhealthy',
            default => "⚪ {$score}",
        };
    }

    /**
     * Load a cached result by key. Returns null if not found or expired.
     *
     * @return array<mixed>|null
     */
    private function loadCache(string $key): ?array
    {
        $path = $this->cachePath($key);

        if (! file_exists($path)) {
            return null;
        }

        if ((time() - filemtime($path)) > self::CACHE_TTL) {
            @unlink($path);

            return null;
        }

        $contents = file_get_contents($path);
        $data = json_decode($contents, true);

        return is_array($data) ? $data : null;
    }

    /**
     * Save results to file cache.
     *
     * @param  array<mixed>  $data
     */
    private function saveCache(string $key, array $data): void
    {
        $this->ensureCacheDir();
        file_put_contents($this->cachePath($key), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function cachePath(string $key): string
    {
        return $this->cacheDir.'/'.$key.'.json';
    }

    private function ensureCacheDir(): void
    {
        if (! is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    private function defaultCacheDir(): string
    {
        $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? sys_get_temp_dir();

        return $home.'/.ensemble/cache/laraplugins';
    }
}
