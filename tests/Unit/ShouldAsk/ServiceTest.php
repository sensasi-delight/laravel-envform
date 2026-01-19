<?php

declare(strict_types=1);

namespace Tests\Unit\ShouldAsk;

use EnvForm\FormValue\Service as FormValueService;
use EnvForm\Registry\RepositoryContract as RegistryRepositoryContract;
use EnvForm\Registry\Service as RegistryService;
use EnvForm\ShouldAsk\RepositoryContract;
use EnvForm\ShouldAsk\Service;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

final class ServiceTest extends TestCase
{
    private FormValueService $formValue;

    private RegistryService $registry;

    /** @var RepositoryContract&MockObject */
    private RepositoryContract $dependencyRepository;

    /** @var RegistryRepositoryContract&MockObject */
    private RegistryRepositoryContract $registryRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->formValue = new FormValueService;
        $this->dependencyRepository = $this->createMock(RepositoryContract::class);
        $this->registryRepository = $this->createMock(RegistryRepositoryContract::class);
    }

    public function test_it_always_returns_true_if_no_dependency_rules_match(): void
    {
        $this->dependencyRepository->method('getMap')->willReturn([
            'cache.stores.*' => 'cache.default',
        ]);

        $this->setupRegistryWith([
            [
                'envKey' => 'APP_NAME',
                'configKey' => 'app.name',
            ],
        ]);

        $service = new Service(
            $this->formValue,
            $this->registry,
            $this->dependencyRepository
        );

        $var = $this->registry->all()->firstWhere('key', 'APP_NAME');
        $this->assertNotNull($var);

        $this->assertTrue($service->isVisible($var));
    }

    public function test_it_returns_false_if_trigger_value_does_not_match_dependency(): void
    {
        $this->dependencyRepository->method('getMap')->willReturn([
            'cache.stores.*' => 'cache.default',
        ]);

        $depMap = [
            'cache.default' => ['redis' => ['cache.stores.redis.*']],
        ];

        // We must register the Trigger Variable (CACHE_DRIVER) so the service can resolve it
        $this->setupRegistryWith([
            [
                'envKey' => 'CACHE_DRIVER',
                'configKey' => 'cache.default',
            ],
            [
                'envKey' => 'REDIS_HOST',
                'configKey' => 'cache.stores.redis.host',
            ],
        ], $depMap);

        // FormValue stores by ENV KEY
        $this->formValue->set('CACHE_DRIVER', 'file');

        $service = new Service(
            $this->formValue,
            $this->registry,
            $this->dependencyRepository
        );

        $var = $this->registry->all()->firstWhere('key', 'REDIS_HOST');
        $this->assertNotNull($var);

        $this->assertFalse($service->isVisible($var));
    }

    /**
     * @param  array<int, array{envKey: string, configKey: string}>  $items
     * @param  array<string, array<string, array<int, string>>>  $depMap
     */
    private function setupRegistryWith(array $items, array $depMap = []): void
    {
        $findings = collect($items)->map(fn ($item) => [
            'envKey' => $item['envKey'],
            'configKey' => $item['configKey'],
            'defaultValue' => null,
            'file' => 'test.php',
        ]);

        $this->registryRepository->method('scan')->willReturn($findings);
        $this->registryRepository->method('getDependencyMap')->willReturn($depMap);

        $this->registry = new RegistryService($this->registryRepository);
    }
}
