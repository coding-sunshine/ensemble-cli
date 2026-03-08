<?php

namespace CodingSunshine\Ensemble\Tests;

use CodingSunshine\Ensemble\Http\LaraPluginsClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class LaraPluginsClientTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheDir = sys_get_temp_dir().'/ensemble-lp-cache-'.uniqid();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->cacheDir)) {
            array_map('unlink', glob($this->cacheDir.'/*.json') ?: []);
            @rmdir($this->cacheDir);
        }
    }

    private function makeClient(array $responses): LaraPluginsClient
    {
        $mock = new MockHandler($responses);
        $http = new Client(['handler' => HandlerStack::create($mock)]);

        return new LaraPluginsClient($http, $this->cacheDir);
    }

    // -------------------------------------------------------------------------
    // search()
    // -------------------------------------------------------------------------

    public function test_search_returns_results_from_api(): void
    {
        $payload = [
            ['name' => 'spatie/laravel-permission', 'description' => 'Roles and permissions', 'health_score' => 'healthy'],
            ['name' => 'laravel/sanctum', 'description' => 'API auth', 'health_score' => 'healthy'],
        ];

        $client = $this->makeClient([
            new Response(200, [], json_encode(['data' => $payload])),
        ]);

        $results = $client->search('auth');

        $this->assertCount(2, $results);
        $this->assertSame('spatie/laravel-permission', $results[0]['name']);
    }

    public function test_search_returns_empty_on_http_failure(): void
    {
        $mock = new MockHandler([
            new \GuzzleHttp\Exception\ConnectException('Connection failed', new \GuzzleHttp\Psr7\Request('GET', '/')),
        ]);
        $http = new Client(['handler' => HandlerStack::create($mock)]);
        $client = new LaraPluginsClient($http, $this->cacheDir);

        $results = $client->search('auth');

        $this->assertSame([], $results);
    }

    public function test_search_caches_result_and_avoids_second_request(): void
    {
        $payload = [['name' => 'spatie/laravel-permission', 'description' => 'Roles']];

        // Only one response available — second call would throw if not cached
        $client = $this->makeClient([
            new Response(200, [], json_encode(['data' => $payload])),
        ]);

        $first = $client->search('roles');
        $second = $client->search('roles');

        $this->assertSame($first, $second);
    }

    // -------------------------------------------------------------------------
    // getDetails()
    // -------------------------------------------------------------------------

    public function test_get_details_returns_package_data(): void
    {
        $payload = ['name' => 'spatie/laravel-permission', 'health_score' => 'healthy', 'downloads' => 12345];

        $client = $this->makeClient([
            new Response(200, [], json_encode(['data' => $payload])),
        ]);

        $details = $client->getDetails('spatie/laravel-permission');

        $this->assertIsArray($details);
        $this->assertSame('spatie/laravel-permission', $details['name']);
        $this->assertSame(12345, $details['downloads']);
    }

    public function test_get_details_returns_null_on_http_failure(): void
    {
        $mock = new MockHandler([
            new \GuzzleHttp\Exception\ConnectException('Connection failed', new \GuzzleHttp\Psr7\Request('GET', '/')),
        ]);
        $http = new Client(['handler' => HandlerStack::create($mock)]);
        $client = new LaraPluginsClient($http, $this->cacheDir);

        $details = $client->getDetails('spatie/laravel-permission');

        $this->assertNull($details);
    }

    public function test_get_details_caches_result(): void
    {
        $payload = ['name' => 'spatie/laravel-permission'];

        $client = $this->makeClient([
            new Response(200, [], json_encode(['data' => $payload])),
        ]);

        $first = $client->getDetails('spatie/laravel-permission');
        $second = $client->getDetails('spatie/laravel-permission');

        $this->assertSame($first, $second);
    }

    // -------------------------------------------------------------------------
    // formatHealthScore()
    // -------------------------------------------------------------------------

    public function test_format_health_score_healthy(): void
    {
        $client = $this->makeClient([]);
        $this->assertSame('🟢 Healthy', $client->formatHealthScore('healthy'));
        $this->assertSame('🟢 Healthy', $client->formatHealthScore('good'));
        $this->assertSame('🟢 Healthy', $client->formatHealthScore('high'));
    }

    public function test_format_health_score_medium(): void
    {
        $client = $this->makeClient([]);
        $this->assertSame('🟡 Medium', $client->formatHealthScore('medium'));
        $this->assertSame('🟡 Medium', $client->formatHealthScore('moderate'));
        $this->assertSame('🟡 Medium', $client->formatHealthScore('fair'));
    }

    public function test_format_health_score_unhealthy(): void
    {
        $client = $this->makeClient([]);
        $this->assertSame('🔴 Unhealthy', $client->formatHealthScore('unhealthy'));
        $this->assertSame('🔴 Unhealthy', $client->formatHealthScore('poor'));
        $this->assertSame('🔴 Unhealthy', $client->formatHealthScore('low'));
    }

    public function test_format_health_score_unknown(): void
    {
        $client = $this->makeClient([]);
        $this->assertSame('⚪ experimental', $client->formatHealthScore('experimental'));
    }
}
