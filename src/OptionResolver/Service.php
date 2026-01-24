<?php

declare(strict_types=1);

namespace EnvForm\OptionResolver;

use EnvForm\DTO\EnvVar;
use EnvForm\Registry;

final readonly class Service
{
    private const METADATA_BLACKLIST = [
        'database.redis' => ['client', 'options', 'clusters'],
    ];

    private const DEPENDENCY_MAP = [
        'cache.default' => 'cache.stores',
        'database.default' => 'database.connections',
        'filesystem.default' => 'filesystem.disks',
        'logging.default' => 'logging.channels',
        'mail.default' => 'mail.mailers',
        'queue.default' => 'queue.stores',
        'cache.stores.redis.connection' => 'database.redis',
        'cache.stores.redis.lock_connection' => 'database.redis',
    ];

    private const NULLABLE_OVERRIDES = [
        'cache.default',
        'database.default',
    ];

    public function __construct(
        private Registry\Service $registry,
    ) {}

    /**
     * @return array<string, string>
     */
    public function resolve(string $configKey): array
    {
        $keys = $this->registry->getStaticKeys($configKey);
        $options = [];
        $blacklist = self::METADATA_BLACKLIST[$configKey] ?? [];

        foreach ($keys as $key) {
            if (\in_array($key, $blacklist, true)) {
                continue;
            }

            $options[$key] = $key;
        }

        ksort($options);

        return $options;
    }

    /**
     * @return array<string, string|null>|null
     */
    public function resolveOptions(EnvVar $envVar): ?array
    {
        $ref = null;

        foreach ($envVar->configKeys as $configKey) {
            if (! empty(self::DEPENDENCY_MAP[$configKey])) {
                $ref = self::DEPENDENCY_MAP[$configKey];
                break;
            }
        }

        if (! $ref && preg_match('/^DB_(.*)_CONNECTION$/', $envVar->key)) {
            $ref = 'database.connections';
        }

        if (! $ref) {
            return null;
        }

        $options = $this->resolve($ref);

        if ($options === []) {
            throw new \EnvForm\Exceptions\BackToMenuException("No options available for {$envVar->key} (via {$ref})");
        }

        if ($this->isNullable($envVar)) {
            $options = ['null' => null] + $options;
        }

        return $options;
    }

    private function isNullable(EnvVar $envVar): bool
    {
        foreach ($envVar->configKeys as $configKey) {
            // Check AST default value
            $astVal = $this->registry->getStaticValue($configKey);
            if ($astVal === null) {
                return true;
            }

            // Check internal nullable overrides
            if (\in_array($configKey, self::NULLABLE_OVERRIDES, true)) {
                return true;
            }
        }

        return false;
    }
}
