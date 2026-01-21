<?php

declare(strict_types=1);

namespace EnvForm\OptionResolver;

use EnvForm\DTO\EnvVar;
use EnvForm\Registry;

final readonly class Service
{
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

        foreach ($keys as $key) {
            $options[$key] = $key;
        }

        return $options;
    }

    /**
     * @return array<string, string>|null
     */
    public function resolveOptions(EnvVar $envVar): ?array
    {
        $map = [
            'cache.default' => 'cache.stores',
            'database.default' => 'database.connections',
            'filesystem.default' => 'filesystem.disks',
            'logging.default' => 'logging.channels',
            'mail.default' => 'mail.mailers',
            'queue.default' => 'queue.stores',
            'cache.stores.redis.connection' => 'database.redis',
            'cache.stores.redis.lock_connection' => 'database.redis',
        ];

        $ref = null;

        foreach ($envVar->configKeys as $configKey) {
            if (! empty($map[$configKey])) {
                $ref = $map[$configKey];
                break;
            }
        }

        if (! $ref && preg_match('/^DB_(.*)_CONNECTION$/', $envVar->key)) {
            $ref = 'database.connections';
        }

        if (! $ref) {
            return null;
        }

        return $this->resolve($ref);
    }
}
