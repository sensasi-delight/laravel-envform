<?php

declare(strict_types=1);

namespace Tests\Unit\ServiceDetection;

use EnvForm\ServiceDetection\DTO\ServiceDefinition;
use EnvForm\ServiceDetection\Repository;
use EnvForm\ServiceDetection\Service;
use EnvForm\ValueResolver\ValueResolverInterface as ValueResolver;
use PHPUnit\Framework\TestCase;

class ServiceTest extends TestCase
{
    /** @var Repository&\PHPUnit\Framework\MockObject\MockObject */
    private $repository;

    /** @var ValueResolver&\PHPUnit\Framework\MockObject\MockObject */
    private $valueResolver;

    private Service $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(Repository::class);
        $this->valueResolver = $this->createMock(ValueResolver::class);
        $this->service = new Service($this->repository, $this->valueResolver);
    }

    public function test_it_identifies_active_service_via_activator(): void
    {
        $this->repository->method('getMap')->willReturn([
            'redis' => new ServiceDefinition(
                'redis',
                ['cache.default' => ['redis']],
                [],
                ['database.redis.*']
            ),
        ]);

        $this->valueResolver->method('resolve')->with('cache.default')->willReturn('redis');

        $this->assertTrue($this->service->isActive('redis'));
        $this->assertTrue($this->service->isKeyRelevant('database.redis.host'));
    }

    public function test_it_identifies_inactive_service(): void
    {
        $this->repository->method('getMap')->willReturn([
            'redis' => new ServiceDefinition(
                'redis',
                ['cache.default' => ['redis']],
                [],
                ['database.redis.*']
            ),
        ]);

        $this->valueResolver->method('resolve')->with('cache.default')->willReturn('file');

        $this->assertFalse($this->service->isActive('redis'));
        $this->assertFalse($this->service->isKeyRelevant('database.redis.host'));
    }

    public function test_it_identifies_active_service_via_master_key(): void
    {
        $this->repository->method('getMap')->willReturn([
            'redis' => new ServiceDefinition(
                'redis',
                ['cache.default' => ['redis']],
                ['database.redis.default.host'],
                ['database.redis.*']
            ),
        ]);

        $this->valueResolver->method('resolve')->with('cache.default')->willReturn('file');
        $this->valueResolver->method('has')->with('database.redis.default.host')->willReturn(true);

        $this->assertTrue($this->service->isActive('redis'));
        $this->assertTrue($this->service->isKeyRelevant('database.redis.host'));
    }

    public function test_unmapped_keys_are_always_relevant(): void
    {
        $this->repository->method('getMap')->willReturn([]);

        $this->assertTrue($this->service->isKeyRelevant('app.name'));
    }
}
