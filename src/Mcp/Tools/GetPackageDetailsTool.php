<?php

namespace CodingSunshine\Ensemble\Mcp\Tools;

use CodingSunshine\Ensemble\Http\LaraPluginsClient;

class GetPackageDetailsTool implements McpToolInterface
{
    public function name(): string
    {
        return 'get_package_details';
    }

    public function description(): string
    {
        return <<<'DESC'
Fetch detailed information about a specific Laravel package from laraplugins.io.

Returns package metadata including:
  - package_name: Composer vendor/package identifier
  - description: full package description
  - health_score: "healthy" | "medium" | "unhealthy"
  - downloads: total Packagist download count
  - stars: GitHub star count
  - latest_version: latest tagged release
  - url: package homepage or GitHub URL

Returns null if the package is not found on laraplugins.io.

Use this tool to verify a package's health before recommending it, or to enrich
recipe information shown to the user.
DESC;
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'package' => [
                    'type' => 'string',
                    'description' => 'Composer package name in vendor/name format, e.g. "spatie/laravel-permission"',
                ],
            ],
            'required' => ['package'],
        ];
    }

    public function execute(array $arguments): mixed
    {
        $package = $arguments['package'] ?? '';

        if ($package === '') {
            return null;
        }

        $client = new LaraPluginsClient();
        $details = $client->getDetails($package);

        if ($details === null) {
            return null;
        }

        $health = $details['health_score'] ?? $details['health'] ?? null;

        if ($health !== null) {
            $details['health_badge'] = $client->formatHealthScore((string) $health);
        }

        return $details;
    }
}
