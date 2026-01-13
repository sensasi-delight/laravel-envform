<?php

declare(strict_types=1);

namespace EnvForm\Services;

use EnvForm\DTO\EnvKeyDefinition;
use Illuminate\Support\Collection;

final class DependencyResolver
{
    /**
     * Rules definition:
     * 'config.path' => [
     *    'value' => ['dependent.config.path.wildcard.*']
     * ]
     *
     * @var array<string, array<string, array<int, string>>>
     */
    private const RULES = [
        'cache.default' => [
            'redis' => ['cache.stores.redis.*', 'database.redis.*'],
            'memcached' => ['cache.stores.memcached.*'],
            'dynamodb' => ['cache.stores.dynamodb.*', 'services.dynamodb.*'],
        ],
        'database.default' => [
            'mysql' => ['database.connections.mysql.*'],
            'pgsql' => ['database.connections.pgsql.*'],
            'sqlsrv' => ['database.connections.sqlsrv.*'],
            'mariadb' => ['database.connections.mariadb.*'],
            'sqlite' => ['database.connections.sqlite.*'],
        ],
        'queue.default' => [
            'database' => ['queue.connections.database.*'],
            'beanstalkd' => ['queue.connections.beanstalkd.*'],
            'sqs' => ['queue.connections.sqs.*', 'services.sqs.*'],
            'redis' => ['queue.connections.redis.*'],
        ],
        'mail.default' => [
            'smtp' => ['mail.mailers.smtp.*'],
            'ses' => ['mail.mailers.ses.*', 'services.ses.*'],
            'mailgun' => ['mail.mailers.mailgun.*', 'services.mailgun.*'],
            'postmark' => ['mail.mailers.postmark.*', 'services.postmark.*'],
        ],
        'filesystem.default' => [
            's3' => ['filesystems.disks.s3.*', 'services.s3.*'],
        ],
    ];

    /**
     * @param  Collection<string, EnvKeyDefinition>  $parsedConfig
     * @param  array<string, mixed>  $currentValues
     */
    public function shouldAsk(string $envKey, Collection $parsedConfig, array $currentValues): bool
    {
        $item = $parsedConfig->firstWhere('key', $envKey);
        if (! $item) {
            return true;
        }

        $paths = $item->configPaths;
        if (empty($paths) && ! empty($item->configPath)) {
            $paths = [$item->configPath];
        }

        if (empty($paths) || (count($paths) === 1 && empty($paths[0]))) {
            return true;
        }

        // If ANY usage path is active, we ask for the key.
        foreach ($paths as $path) {
            if ($this->isPathActive((string) $path, $parsedConfig, $currentValues)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<string, EnvKeyDefinition>  $parsedConfig
     * @param  array<string, mixed>  $currentValues
     */
    private function isPathActive(string $configPath, Collection $parsedConfig, array $currentValues): bool
    {
        if (empty($configPath)) {
            return true;
        }

        // Check explicit allow rules
        foreach (self::RULES as $triggerPath => $conditions) {
            $triggerItem = $parsedConfig->firstWhere('configPath', $triggerPath);

            if ($triggerItem) {
                $triggerKey = $triggerItem->key;
                $triggerValue = $currentValues[$triggerKey] ?? null;

                if ($triggerValue) {
                    foreach ($conditions as $expectedValue => $patterns) {
                        if ($this->matchesPatterns($configPath, $patterns)) {
                            // This rule governs this path.
                            // It matches the pattern.
                            // Valid only if value matches expected.
                            return (string) $triggerValue === (string) $expectedValue;
                        }
                    }
                }
            }
        }

        // Check implicit deny (if it matches a rule pattern but not the one selected above)
        foreach (self::RULES as $triggerPath => $conditions) {
            foreach ($conditions as $expectedValue => $patterns) {
                if ($this->matchesPatterns($configPath, $patterns)) {
                    // It matches a restrictive pattern (e.g. 'cache.stores.redis.*')
                    // But we didn't return true above.
                    // This means either:
                    // 1. Trigger key not found (fallback to allow? No, usually hidden)
                    // 2. Trigger value not set (fallback to allow? No)
                    // 3. Trigger value set to something else (e.g. 'database')

                    // If it matches a restrictive pattern, it defaults to HIDDEN unless explicitly allowed above.
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check if the given Env Key is a trigger for other dependencies.
     *
     * @param  Collection<string, EnvKeyDefinition>  $parsedConfig
     */
    public function isTrigger(string $envKey, Collection $parsedConfig): bool
    {
        $item = $parsedConfig->firstWhere('key', $envKey);
        if (! $item) {
            return false;
        }

        // match existing logic
        $paths = $item->configPaths;
        if (empty($paths) && ! empty($item->configPath)) {
            $paths = [$item->configPath];
        }

        foreach ($paths as $path) {
            if (array_key_exists($path, self::RULES)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  string[]  $patterns
     */
    private function matchesPatterns(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}
