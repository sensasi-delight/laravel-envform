<?php

declare(strict_types=1);

namespace EnvForm\ServiceDetection;

interface ServiceDetectionInterface
{
    /**
     * Determine if a specific service is currently active in the application.
     */
    public function isActive(string $serviceName): bool;

    /**
     * Determine if a configuration key belongs to an active service (or no service).
     * Returns false ONLY if the key belongs to an explicit service that is NOT active.
     */
    public function isKeyRelevant(string $configKey): bool;

    /**
     * Refresh the service detection context.
     */
    public function refresh(): void;
}
