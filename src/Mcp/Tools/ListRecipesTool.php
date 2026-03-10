<?php

namespace CodingSunshine\Ensemble\Mcp\Tools;

use CodingSunshine\Ensemble\Recipes\KnownRecipes;

class ListRecipesTool implements McpToolInterface
{
    public function name(): string
    {
        return 'list_recipes';
    }

    public function description(): string
    {
        return 'List available Ensemble recipes (e.g. roles-permissions, saas-billing).';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [],
        ];
    }

    public function execute(array $arguments): mixed
    {
        return KnownRecipes::all();
    }
}
