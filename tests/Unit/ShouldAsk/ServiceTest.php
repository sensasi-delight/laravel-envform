<?php

declare(strict_types=1);

namespace Tests\Unit\ShouldAsk;

use EnvForm\FormValue\Service as FormValueService;
use EnvForm\Registry\RepositoryContract as RegistryRepositoryContract;
use EnvForm\Registry\Service as RegistryService;
use EnvForm\ShouldAsk\RepositoryContract as ShouldAskRepositoryContract;
use EnvForm\ShouldAsk\Service;
use Tests\TestCase;

final class ServiceTest extends TestCase
{
    private FormValueService $formValue;

    private RegistryService $registry;

    private ShouldAskRepositoryContract $dependencyRepository;

    private RegistryRepositoryContract $registryRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->formValue = new FormValueService;
        $this->dependencyRepository = $this->createMock(ShouldAskRepositoryContract::class);
        $this->registryRepository = $this->createMock(RegistryRepositoryContract::class);
    }

    public function test_it_always_returns_true_if_no_dependency_rules_match(): void
    {
        // Scenario: APP_NAME has no dependencies.

        $this->dependencyRepository->method('getMap')->willReturn([
            'cache.stores.*' => 'cache.default',
        ]);

        $this->setupRegistryWith('APP_NAME', 'app.name');

        $service = new Service(
            $this->formValue,
            $this->registry,
            $this->dependencyRepository
        );

        $var = $this->registry->all()->firstWhere('key', 'APP_NAME');

        $this->assertTrue($service->isVisible($var));
    }

    public function test_it_returns_false_if_trigger_value_does_not_match_dependency(): void
    {
        // Scenario: REDIS_HOST matches 'cache.stores.redis.*'
        // Rule: 'cache.stores.*' => 'cache.default'
        // Trigger: 'cache.default' is 'file' (mismatch)

        $this->dependencyRepository->method('getMap')->willReturn([
            'cache.stores.*' => 'cache.default',
        ]);

        // Registry map for hydration
        $depMap = [
            'cache.default' => ['redis' => ['cache.stores.redis.*']],
        ];

        $this->setupRegistryWith('REDIS_HOST', 'cache.stores.redis.host', $depMap);

        // Set trigger value
        $this->formValue->set('cache.default', 'file');

        $service = new Service(
            $this->formValue,
            $this->registry,
            $this->dependencyRepository
        );

        $var = $this->registry->all()->firstWhere('key', 'REDIS_HOST');

        $this->assertFalse($service->isVisible($var));
    }

    private function setupRegistryWith(string $envKey, string $configKey, array $depMap = []): void
    {
        $findings = collect([
            [
                'envKey' => $envKey,
                'configKey' => $configKey,
                'defaultValue' => null,
                'file' => 'test.php',
            ],
        ]);

        $this->registryRepository->method('scan')->willReturn($findings);
        $this->registryRepository->method('getDependencyMap')->willReturn($depMap);

        $this->registry = new RegistryService($this->registryRepository);
    }
}
