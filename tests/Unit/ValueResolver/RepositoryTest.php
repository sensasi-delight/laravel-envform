<?php

declare(strict_types=1);

namespace Tests\Unit\ValueResolver;

use EnvForm\ValueResolver\Repository;

final class RepositoryTest extends ValueResolverTestCase
{
    final public function test_it_can_load_all_rules(): void
    {
        $repo = new Repository([__DIR__.'/../../../resources']);
        $rules = $repo->all();

        $this->assertArrayHasKey('cache.stores.database.lock_table', $rules);
        $this->assertInstanceOf(\Closure::class, $rules['cache.stores.database.lock_table']);
    }

    final public function test_it_can_find_a_specific_rule(): void
    {
        $repo = new Repository([__DIR__.'/../../../resources']);
        $rule = $repo->find('cache.stores.database.lock_table');

        $this->assertInstanceOf(\Closure::class, $rule);
    }

    final public function test_it_returns_null_when_rule_not_found(): void
    {
        $repo = new Repository([__DIR__.'/../../../resources']);
        $rule = $repo->find('non.existent.key');

        $this->assertNull($rule);
    }
}
