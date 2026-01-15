<?php

declare(strict_types=1);

namespace EnvForm\Services;

use EnvForm\DTO\EnvKeyDefinition;

final class DependencyResolver
{
    /**
     * Rules definition:
     * 'config.path' => [
     *    'value' => ['dependent.config.path.wildcard.*']
     * ]
     */
    private const RULES = [
        'cache.default' => [
            'array' => ['cache.stores.array.*'],
            'database' => ['cache.stores.database.*'],
            'file' => ['cache.stores.file.*'],
            'memcached' => ['cache.stores.memcached.*'],
            'redis' => ['cache.stores.redis.*', 'database.redis.*'],
            'dynamodb' => ['cache.stores.dynamodb.*', 'services.dynamodb.*'],
            'octane' => ['cache.stores.octane.*'],
            'failover' => ['cache.stores.failover.*'],
            'null' => ['cache.stores.null.*'],
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

    public function __construct(
        private readonly KeyManager $keyManager
    ) {}

    /**
     * Filter out any keys that shouldn't be asked for.
     */
    final public function shouldAsk(
        EnvKeyDefinition $envDef
    ): bool {
        $rules = self::RULES;

        $dependentPatterns = collect($rules)->flatten()->toArray();
        $isEnvDefHasDependant = collect($envDef->configKeys)
            ->contains(
                fn (string $configKey) => $this->matchesPatterns(
                    $configKey,
                    $dependentPatterns
                )
            );

        if (! $isEnvDefHasDependant) {
            return true; // No dependant considered as true
        }

        foreach ($envDef->configKeys as $configKey) {
            foreach ($rules as $dependantConfigKey => $conditions) {
                $dependantEnvKey = $this->keyManager->getDefinitionByConfigKey(
                    $dependantConfigKey
                );

                if (! $dependantEnvKey) {
                    continue;
                }

                $collectedDependantValue = (string) $this->keyManager
                    ->getFormValue(
                        $dependantEnvKey->key
                    );

                if (! $collectedDependantValue) {
                    continue;
                }

                $patterns = $conditions[$collectedDependantValue] ?? null;

                if ($patterns === null) {
                    continue;
                }

                if ($this->matchesPatterns(
                    $configKey,
                    $patterns
                )) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if the given Env Key is a trigger for other dependencies.
     */
    public function isTrigger(EnvKeyDefinition $endDef): bool
    {
        $paths = $this->resolveConfigKeys($endDef);
        $rules = self::RULES;

        foreach ($paths as $path) {
            if (\array_key_exists($path, $rules)) {
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

    /**
     * @return string[]
     */
    private function resolveConfigKeys(EnvKeyDefinition $item): array
    {
        $configKeys = $item->configKeys;

        // Fallback to legacy single configKey if empty, though DTO should handle this.
        if (empty($configKeys) && ! empty($item->configKey)) {
            $configKeys = [$item->configKey];
        }

        return array_filter($configKeys);
    }
}
