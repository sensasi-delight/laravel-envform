<?php

declare(strict_types=1);

namespace Tests\Unit;

use EnvForm\Contracts\FormValueProvider;
use EnvForm\DTO\EnvKeyDefinition;
use EnvForm\Services\DependencyResolver;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class DependencyResolverTest extends TestCase
{
    public function test_it_always_asks_if_key_has_no_dependencies(): void
    {
        $mockProvider = $this->createMockProvider();
        $resolver = new DependencyResolver($mockProvider);
        
        $envDef = $this->createDef('APP_NAME', '');

        $this->assertTrue($resolver->shouldAsk($envDef));
    }

    public function test_it_skips_dependent_key_if_trigger_is_missing(): void
    {
        // REDIS_HOST depends on cache.default=redis
        // cache.default is mapped to CACHE_STORE
        // CACHE_STORE is not set, so REDIS_HOST should be skipped
        
        $mockProvider = $this->createMockProvider();
        $resolver = new DependencyResolver($mockProvider);
        
        $envDef = $this->createDef('REDIS_HOST', 'database.redis.default.host');

        $this->assertFalse($resolver->shouldAsk($envDef));
    }

    public function test_it_asks_dependent_key_if_trigger_matches(): void
    {
        // CACHE_STORE='redis' → REDIS_HOST should be asked
        $mockProvider = $this->createMockProvider(
            formValues: ['CACHE_STORE' => 'redis'],
            definitions: [
                'cache.default' => $this->createDef('CACHE_STORE', 'cache.default'),
            ]
        );

        $resolver = new DependencyResolver($mockProvider);
        $redisHostDef = $this->createDef('REDIS_HOST', 'database.redis.default.host');

        $this->assertTrue($resolver->shouldAsk($redisHostDef));
    }

    public function test_it_skips_dependent_key_if_trigger_mismatches(): void
    {
        // CACHE_STORE='database' → REDIS_HOST should be skipped
        $mockProvider = $this->createMockProvider(
            formValues: ['CACHE_STORE' => 'database'],
            definitions: [
                'cache.default' => $this->createDef('CACHE_STORE', 'cache.default'),
            ]
        );

        $resolver = new DependencyResolver($mockProvider);
        $redisHostDef = $this->createDef('REDIS_HOST', 'database.redis.default.host');

        $this->assertFalse($resolver->shouldAsk($redisHostDef));
    }

    public function test_it_identifies_trigger_keys(): void
    {
        $mockProvider = $this->createMockProvider();
        $resolver = new DependencyResolver($mockProvider);

        $cacheStoreDef = $this->createDef('CACHE_STORE', 'cache.default');
        $redisHostDef = $this->createDef('REDIS_HOST', 'database.redis.default.host');

        $this->assertTrue($resolver->isTrigger($cacheStoreDef));
        $this->assertFalse($resolver->isTrigger($redisHostDef));
    }

    public function test_it_handles_multiple_config_paths_for_single_key(): void
    {
        // AWS_KEY is used in 'cache.dynamodb' AND 'mail.ses'
        $awsKeyDef = $this->createDef(
            key: 'AWS_KEY',
            configKey: '',
            configKeys: ['cache.stores.dynamodb.key', 'mail.mailers.ses.key']
        );

        // Scenario 1: Both triggers OFF (CACHE=database, MAIL=smtp)
        $mockProvider = $this->createMockProvider(
            formValues: ['CACHE_STORE' => 'database', 'MAIL_MAILER' => 'smtp'],
            definitions: [
                'cache.default' => $this->createDef('CACHE_STORE', 'cache.default'),
                'mail.default' => $this->createDef('MAIL_MAILER', 'mail.default'),
            ]
        );
        $resolver = new DependencyResolver($mockProvider);
        $this->assertFalse($resolver->shouldAsk($awsKeyDef));

        // Scenario 2: One trigger ON (Cache=dynamodb)
        $mockProvider = $this->createMockProvider(
            formValues: ['CACHE_STORE' => 'dynamodb', 'MAIL_MAILER' => 'smtp'],
            definitions: [
                'cache.default' => $this->createDef('CACHE_STORE', 'cache.default'),
                'mail.default' => $this->createDef('MAIL_MAILER', 'mail.default'),
            ]
        );
        $resolver = new DependencyResolver($mockProvider);
        $this->assertTrue($resolver->shouldAsk($awsKeyDef));

        // Scenario 3: One trigger ON (Mail=ses)
        $mockProvider = $this->createMockProvider(
            formValues: ['CACHE_STORE' => 'database', 'MAIL_MAILER' => 'ses'],
            definitions: [
                'cache.default' => $this->createDef('CACHE_STORE', 'cache.default'),
                'mail.default' => $this->createDef('MAIL_MAILER', 'mail.default'),
            ]
        );
        $resolver = new DependencyResolver($mockProvider);
        $this->assertTrue($resolver->shouldAsk($awsKeyDef));
    }

    private function createMockProvider(
        array $formValues = [],
        array $definitions = []
    ): FormValueProvider {
        return new class($formValues, $definitions) implements FormValueProvider {
            public function __construct(
                private array $formValues,
                private array $definitions
            ) {}

            public function getFormValue(string $envKey): mixed
            {
                return $this->formValues[$envKey] ?? null;
            }

            public function getDefinitionByConfigKey(string $configKey): ?EnvKeyDefinition
            {
                return $this->definitions[$configKey] ?? null;
            }
        };
    }

    private function createDef(
        string $key,
        string $configKey,
        array $configKeys = []
    ): EnvKeyDefinition {
        return new EnvKeyDefinition(
            key: $key,
            default: null,
            file: 'test.php',
            description: 'test',
            group: 'test',
            configKey: $configKey,
            configKeys: $configKeys,
            currentValue: Config::get($key),
        );
    }
}
