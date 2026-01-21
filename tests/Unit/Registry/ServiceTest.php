<?php

declare(strict_types=1);

namespace Tests\Unit\Registry;

use EnvForm\Registry\Repository;
use EnvForm\Registry\Service;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

final class ServiceTest extends TestCase
{
    public function test_it_hydrates_env_vars_from_repository_findings(): void
    {
        $findings = collect([
            [
                'envKey' => 'APP_NAME',
                'configKey' => 'app.name',
                'defaultValue' => 'Laravel',
                'file' => 'app.php',
            ],
            [
                'envKey' => 'DB_HOST',
                'configKey' => 'database.connections.mysql.host',
                'defaultValue' => '127.0.0.1',
                'file' => 'database.php',
            ],
        ]);

        /** @var Repository&MockObject $repo */
        $repo = $this->createMock(Repository::class);
        $repo->method('scan')->willReturn($findings);
        $repo->method('getDependencyMap')->willReturn([
            'cache.stores.redis.*' => 'cache.default',
        ]);

        $service = new Service($repo);
        $vars = $service->all();

        $this->assertCount(2, $vars);

        $appName = $vars->firstWhere('key', 'APP_NAME');
        $this->assertNotNull($appName);
        $this->assertSame('Laravel', $appName->default);
        $this->assertSame('app.php', $appName->group);
        $this->assertTrue($appName->configKeys->contains('app.name'));

        $dbHost = $vars->firstWhere('key', 'DB_HOST');
        $this->assertNotNull($dbHost);
        $this->assertSame('127.0.0.1', $dbHost->default);
    }

    public function test_it_identifies_triggers_and_dependencies(): void
    {
        $findings = collect([
            [
                'envKey' => 'CACHE_DRIVER',
                'configKey' => 'cache.default',
                'defaultValue' => 'file',
                'file' => 'cache.php',
            ],
            [
                'envKey' => 'REDIS_HOST',
                'configKey' => 'cache.stores.redis.host',
                'defaultValue' => '127.0.0.1',
                'file' => 'cache.php',
            ],
        ]);

        /** @var Repository&MockObject $repo */
        $repo = $this->createMock(Repository::class);
        $repo->method('scan')->willReturn($findings);
        $repo->method('getDependencyMap')->willReturn([
            'cache.stores.redis.*' => 'cache.default',
        ]);

        $service = new Service($repo);

        $cacheDriver = $service->find('cache.default');
        $this->assertNotNull($cacheDriver);
        $this->assertTrue($cacheDriver->isTrigger);

        $redisHost = $service->find('cache.stores.redis.host');
        $this->assertNotNull($redisHost);
        $this->assertFalse($redisHost->isTrigger);
    }
}
