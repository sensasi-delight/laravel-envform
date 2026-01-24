<?php

declare(strict_types=1);

namespace EnvForm\ServiceDetection\DTO;

/**
 * The runtime evaluated state of all services.
 */
final class ServiceContext
{
    /**
     * @param  string[]  $activeServices  Set of service names currently considered active
     * @param  array<string, string>  $keyToServiceMap  Optimized lookup from config key to service name
     */
    public function __construct(
        public readonly array $activeServices,
        public readonly array $keyToServiceMap,
    ) {}
}
