<?php

declare(strict_types=1);

namespace EnvForm\ServiceDetection\DTO;

/**
 * The static mapping defining a third-party service and its triggers.
 */
final class ServiceDefinition
{
    /**
     * @param  string  $name  Unique identifier (e.g., 'redis', 'aws')
     * @param  array<string, string[]>  $activators  Map of subsystem config keys to expected driver values
     * @param  string[]  $masterKeys  List of config keys that trigger implicit activation if non-null
     * @param  string[]  $patterns  List of config key patterns owned by this service
     */
    public function __construct(
        public readonly string $name,
        public readonly array $activators,
        public readonly array $masterKeys,
        public readonly array $patterns,
    ) {}
}
