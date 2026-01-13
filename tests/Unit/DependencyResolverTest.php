<?php

declare(strict_types=1);

namespace Tests\Unit;

use EnvForm\DTO\EnvKeyDefinition;
use EnvForm\Services\DependencyResolver;
use Tests\TestCase;

final class DependencyResolverTest extends TestCase
{
    public function test_it_always_asks_if_key_has_no_dependencies(): void
    {
        $resolver = new DependencyResolver;
        $parsedConfig = collect([
            $this->createDef('APP_NAME', 'app.name'),
        ]);
        $currentValues = [];

        $this->assertTrue($resolver->shouldAsk('APP_NAME', $parsedConfig, $currentValues));
    }

    public function test_it_skips_dependent_key_if_trigger_is_missing(): void
    {
        $resolver = new DependencyResolver;

        // REDIS_HOST depends on cache.default=redis
        // cache.default is mapped to CACHE_STORE

        $parsedConfig = collect([
            $this->createDef('CACHE_STORE', 'cache.default'),
            $this->createDef('REDIS_HOST', 'database.redis.default.host'),
        ]);

        // CACHE_STORE is not set in currentValues
        $currentValues = [];

        $this->assertFalse($resolver->shouldAsk('REDIS_HOST', $parsedConfig, $currentValues));
    }

    public function test_it_asks_dependent_key_if_trigger_matches(): void
    {
        $resolver = new DependencyResolver;
        $parsedConfig = collect([
            $this->createDef('CACHE_STORE', 'cache.default'),
            $this->createDef('REDIS_HOST', 'database.redis.default.host'),
        ]);

        $currentValues = ['CACHE_STORE' => 'redis'];

        $this->assertTrue($resolver->shouldAsk('REDIS_HOST', $parsedConfig, $currentValues));
    }

    public function test_it_skips_dependent_key_if_trigger_mismatches(): void
    {
        $resolver = new DependencyResolver;
        $parsedConfig = collect([
            $this->createDef('CACHE_STORE', 'cache.default'),
            $this->createDef('REDIS_HOST', 'database.redis.default.host'),
        ]);

        $currentValues = ['CACHE_STORE' => 'database'];

        $this->assertFalse($resolver->shouldAsk('REDIS_HOST', $parsedConfig, $currentValues));
    }

    public function test_it_identifies_trigger_keys(): void
    {
        $resolver = new DependencyResolver;
        $parsedConfig = collect([
            $this->createDef('CACHE_STORE', 'cache.default'),
            $this->createDef('REDIS_HOST', 'database.redis.default.host'),
        ]);

        $this->assertTrue($resolver->isTrigger('CACHE_STORE', $parsedConfig));
        $this->assertFalse($resolver->isTrigger('REDIS_HOST', $parsedConfig));
    }

    public function test_it_handles_multiple_config_paths_for_single_key(): void
    {
        $resolver = new DependencyResolver;

        // AWS_KEY is used in 'cache.dynamodb' AND 'mail.ses'
        $parsedConfig = collect([
            // Triggers
            $this->createDef('CACHE_STORE', 'cache.default'),
            $this->createDef('MAIL_MAILER', 'mail.default'),

            // The Key in Question (has 2 paths)
            $this->createDef(
                key: 'AWS_KEY',
                configPath: '', // Primary logic uses configPaths
                configPaths: ['cache.stores.dynamodb.key', 'mail.mailers.ses.key']
            ),
        ]);

        // Scenario 1: Both triggers OFF
        // CACHE=database, MAIL=smtp
        $currentValues = ['CACHE_STORE' => 'database', 'MAIL_MAILER' => 'smtp'];
        $this->assertFalse($resolver->shouldAsk('AWS_KEY', $parsedConfig, $currentValues));

        // Scenario 2: One trigger ON (Cache=dynamodb)
        $currentValues = ['CACHE_STORE' => 'dynamodb', 'MAIL_MAILER' => 'smtp'];
        $this->assertTrue($resolver->shouldAsk('AWS_KEY', $parsedConfig, $currentValues));

        // Scenario 3: One trigger ON (Mail=ses)
        $currentValues = ['CACHE_STORE' => 'database', 'MAIL_MAILER' => 'ses'];
        $this->assertTrue($resolver->shouldAsk('AWS_KEY', $parsedConfig, $currentValues));
    }

    private function createDef(string $key, string $configPath, array $configPaths = []): EnvKeyDefinition
    {
        return new EnvKeyDefinition(
            key: $key,
            default: null,
            file: 'test.php',
            description: 'test',
            group: 'test',
            configPath: $configPath,
            configPaths: $configPaths
        );
    }
}
