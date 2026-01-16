<?php

declare(strict_types=1);

namespace EnvForm\Services;

use EnvForm\Contracts\EnvRegistryService;
use EnvForm\Contracts\UserSessionService;
use EnvForm\DTO\EnvVar;
use Illuminate\Support\Collection;

/**
 * Deterministic engine for evaluating dependency rules between environment variables.
 * Decides whether a variable should be prompted based on the state of its trigger variables.
 */
final class RuleEngine
{
    /**
     * Rules definition:
     * 'config.path' => [
     *    'value' => ['dependent.config.path.wildcard.*']
     * ]
     */
    public const RULES = [
        'cache.default' => [
            'array' => ['cache.stores.array.*'],
            'database' => ['cache.stores.database.*'],
            'file' => ['cache.stores.file.*'],
            'memcached' => ['cache.stores.memcached.*'],
            'redis' => ['cache.stores.redis.*'],
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
        private readonly UserSessionService $state,
        private readonly EnvRegistryService $registry
    ) {}

    /**
     * Filter out any keys that shouldn't be asked for.
     */
    final public function shouldAsk(
        EnvVar $envDef
    ): bool {
        // If no dependencies, always ask
        if (empty($envDef->dependencies)) {
            return true;
        }

        foreach ($envDef->dependencies as $triggerConfigKey => $valueMap) {
            $triggerEnvKey = $this->registry->find($triggerConfigKey);

            if (! $triggerEnvKey) {
                continue;
            }

            $currentTriggerValue = (string) $this->state->input($triggerEnvKey->key);

            if (! $currentTriggerValue) {
                continue;
            }

            // Check if the current value of the trigger matches any condition for this key
            $patterns = $valueMap[$currentTriggerValue] ?? null;
            if ($patterns && $this->matchesAnyConfigKey($envDef->configKeys, $patterns)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, string>  $configKeys
     * @param  string[]  $patterns
     */
    private function matchesAnyConfigKey(Collection $configKeys, array $patterns): bool
    {
        foreach ($configKeys as $configKey) {
            if ($this->matchesPatterns($configKey, $patterns)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  string[]  $patterns
     */
    private function matchesPatterns(string $configKey, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $configKey)) {
                return true;
            }
        }

        return false;
    }
}
