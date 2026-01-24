<?php

declare(strict_types=1);

namespace Tests\Unit\OptionResolver;

use EnvForm\DTO\EnvVar;
use EnvForm\OptionResolver\Service;
use EnvForm\Registry\Service as RegistryService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class ServiceTest extends TestCase
{
    private Service $service;

    /** @var RegistryService&MockObject */
    private RegistryService $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = $this->createMock(RegistryService::class);

        $this->service = new Service($this->registry);
    }

    public function test_it_sorts_options_alphabetically(): void
    {
        $this->registry->method('getStaticKeys')
            ->with('database.connections')
            ->willReturn(['sqlite', 'mysql', 'pgsql']);

        $options = $this->service->resolve('database.connections');

        $this->assertSame([
            'mysql' => 'mysql',
            'pgsql' => 'pgsql',
            'sqlite' => 'sqlite',
        ], $options);
    }

    public function test_it_filters_metadata_keys_specifically_for_mapped_configs(): void
    {
        // 1. Should filter for database.redis
        $this->registry->method('getStaticKeys')
            ->willReturnMap([
                ['database.redis', ['default', 'cache', 'client', 'options', 'clusters']],
                ['database.connections', ['mysql', 'sqlite', 'options']], // 'options' is a valid key here
            ]);

        $redisOptions = $this->service->resolve('database.redis');

        $this->assertSame([
            'cache' => 'cache',
            'default' => 'default',
        ], $redisOptions);

        $this->assertArrayNotHasKey('client', $redisOptions);
        $this->assertArrayNotHasKey('options', $redisOptions);

        // 2. Should NOT filter for database.connections (where 'options' might be valid)
        $dbOptions = $this->service->resolve('database.connections');
        $this->assertArrayHasKey('options', $dbOptions);
    }

    public function test_it_prepends_null_option_if_field_is_nullable_via_ast(): void
    {
        $envVar = new EnvVar(
            new Collection(['cache.default']),
            null,
            'config/cache.php',
            false,
            'CACHE_DRIVER'
        );

        $this->registry->method('getStaticValue')
            ->with('cache.default')
            ->willReturn(null);

        $this->registry->method('getStaticKeys')
            ->with('cache.stores')
            ->willReturn(['file', 'redis', 'database']);

        $options = $this->service->resolveOptions($envVar);

        $this->assertSame([
            'null' => null,
            'database' => 'database',
            'file' => 'file',
            'redis' => 'redis',
        ], $options);
    }

    public function test_it_prepends_null_option_if_field_is_nullable_via_override(): void
    {
        $envVar = new EnvVar(
            new Collection(['cache.default']),
            'file',
            'config/cache.php',
            false,
            'CACHE_DRIVER'
        );

        $this->registry->method('getStaticValue')
            ->with('cache.default')
            ->willReturn('file');

        $this->registry->method('getStaticKeys')
            ->with('cache.stores')
            ->willReturn(['file', 'redis', 'database']);

        $options = $this->service->resolveOptions($envVar);

        $this->assertSame([
            'null' => null,
            'database' => 'database',
            'file' => 'file',
            'redis' => 'redis',
        ], $options);
    }
}
