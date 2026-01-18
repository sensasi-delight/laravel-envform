<?php

declare(strict_types=1);

namespace Tests\Unit\Wizard;

use EnvForm\DTO\EnvVar;
use EnvForm\FormValue\Service as FormValueService;
use EnvForm\Registry\Service as RegistryService;
use EnvForm\Wizard\ShouldAsk;
use Tests\TestCase;

final class ShouldAskTest extends TestCase
{
    public function test_it_always_returns_true_if_no_dependencies(): void
    {
        $shouldAsk = $this->createShouldAsk();
        $var = $this->createEnvVar(key: 'APP_NAME', dependencies: []);

        $this->assertTrue($shouldAsk->shouldAsk($var));
    }

    public function test_it_returns_false_if_trigger_is_missing_in_registry(): void
    {
        // Scenario: REDIS_HOST depends on CACHE_DRIVER, but CACHE_DRIVER isn't in registry (unlikely but possible)
        $shouldAsk = $this->createShouldAsk();

        $var = $this->createEnvVar(
            key: 'REDIS_HOST',
            dependencies: ['cache.default' => ['redis' => ['cache.stores.redis.*']]]
        );

        // Registry is empty in mock, so 'cache.default' won't be found
        $this->assertFalse($shouldAsk->shouldAsk($var));
    }

    public function test_it_returns_false_if_trigger_value_mismatches(): void
    {
        // Scenario: CACHE_DRIVER=file, so REDIS_HOST should NOT be asked
        $formValue = new FormValueService;
        $formValue->set('CACHE_DRIVER', 'file');

        $registry = $this->createMockRegistryWith('CACHE_DRIVER', 'cache.default');

        $shouldAsk = new ShouldAsk($formValue, $registry);

        $var = $this->createEnvVar(
            key: 'REDIS_HOST',
            configKeys: ['cache.stores.redis.host'],
            dependencies: ['cache.default' => ['redis' => ['cache.stores.redis.*']]]
        );

        $this->assertFalse($shouldAsk->shouldAsk($var));
    }

    public function test_it_returns_true_if_trigger_value_matches(): void
    {
        // Scenario: CACHE_DRIVER=redis, so REDIS_HOST SHOULD be asked
        $formValue = new FormValueService;
        $formValue->set('CACHE_DRIVER', 'redis');

        $registry = $this->createMockRegistryWith('CACHE_DRIVER', 'cache.default');

        $shouldAsk = new ShouldAsk($formValue, $registry);

        $var = $this->createEnvVar(
            key: 'REDIS_HOST',
            configKeys: ['cache.stores.redis.host'],
            dependencies: ['cache.default' => ['redis' => ['cache.stores.redis.*']]]
        );

        $this->assertTrue($shouldAsk->shouldAsk($var));
    }

    // --- Helpers ---

    private function createShouldAsk(?RegistryService $registry = null): ShouldAsk
    {
        $formValue = new FormValueService;
        if (! $registry) {
            $repo = $this->createMock(\EnvForm\Registry\RepositoryContract::class);
            $repo->method('scan')->willReturn(collect());
            $repo->method('getDependencyMap')->willReturn([]);
            $registry = new RegistryService($repo);
        }

        return new ShouldAsk($formValue, $registry);
    }

    private function createMockRegistryWith(string $envKey, string $configKey): RegistryService
    {
        $repo = $this->createMock(\EnvForm\Registry\RepositoryContract::class);
        $repo->method('scan')->willReturn(collect([
            [
                'envKey' => $envKey,
                'configKey' => $configKey,
                'defaultValue' => 'default',
                'file' => 'test.php',
            ],
        ]));
        $repo->method('getDependencyMap')->willReturn([
            'cache.default' => [
                'redis' => ['cache.stores.redis.*'],
            ],
        ]);

        return new RegistryService($repo);
    }

    private function createEnvVar(
        string $key,
        array $configKeys = [],
        array $dependencies = []
    ): EnvVar {
        return new EnvVar(
            collect($configKeys),
            null,
            $dependencies,
            'test.php',
            false,
            $key
        );
    }
}
