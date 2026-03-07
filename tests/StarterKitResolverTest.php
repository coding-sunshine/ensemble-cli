<?php

namespace CodingSunshine\Ensemble\Tests;

use CodingSunshine\Ensemble\Scaffold\StarterKitResolver;
use PHPUnit\Framework\TestCase;

class StarterKitResolverTest extends TestCase
{
    public function test_resolve_returns_package_for_known_stack(): void
    {
        $this->assertSame('laravel/livewire-starter-kit', StarterKitResolver::resolve('livewire'));
        $this->assertSame('laravel/react-starter-kit', StarterKitResolver::resolve('react'));
        $this->assertSame('laravel/vue-starter-kit', StarterKitResolver::resolve('vue'));
        $this->assertSame('laravel/svelte-starter-kit', StarterKitResolver::resolve('svelte'));
    }

    public function test_resolve_returns_null_for_unknown_stack(): void
    {
        $this->assertNull(StarterKitResolver::resolve('angular'));
        $this->assertNull(StarterKitResolver::resolve(''));
    }

    public function test_is_valid_returns_true_for_known_stacks(): void
    {
        $this->assertTrue(StarterKitResolver::isValid('livewire'));
        $this->assertTrue(StarterKitResolver::isValid('react'));
        $this->assertTrue(StarterKitResolver::isValid('vue'));
        $this->assertTrue(StarterKitResolver::isValid('svelte'));
    }

    public function test_is_valid_returns_false_for_unknown_stacks(): void
    {
        $this->assertFalse(StarterKitResolver::isValid('angular'));
        $this->assertFalse(StarterKitResolver::isValid(''));
        $this->assertFalse(StarterKitResolver::isValid('jquery'));
    }

    public function test_stacks_returns_all_supported_stacks(): void
    {
        $stacks = StarterKitResolver::stacks();
        $this->assertContains('livewire', $stacks);
        $this->assertContains('react', $stacks);
        $this->assertContains('vue', $stacks);
        $this->assertContains('svelte', $stacks);
        $this->assertCount(4, $stacks);
    }
}
