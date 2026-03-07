<?php

namespace CodingSunshine\Ensemble\Scaffold;

class StarterKitResolver
{
    /**
     * Map of stack identifiers to their Laravel starter kit package names.
     *
     * @var array<string, string>
     */
    protected const STACK_MAP = [
        'livewire' => 'laravel/livewire-starter-kit',
        'react' => 'laravel/react-starter-kit',
        'vue' => 'laravel/vue-starter-kit',
        'svelte' => 'laravel/svelte-starter-kit',
    ];

    /**
     * Resolve a starter kit package name from a stack identifier.
     */
    public static function resolve(string $stack): ?string
    {
        return self::STACK_MAP[$stack] ?? null;
    }

    /**
     * Determine whether the given stack identifier is a valid, known stack.
     */
    public static function isValid(string $stack): bool
    {
        return isset(self::STACK_MAP[$stack]);
    }

    /**
     * Get all supported stack identifiers.
     *
     * @return array<int, string>
     */
    public static function stacks(): array
    {
        return array_keys(self::STACK_MAP);
    }
}
