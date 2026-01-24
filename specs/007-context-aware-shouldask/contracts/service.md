# Contracts: Service Detection

## ServiceDetection\Service
The primary orchestrator for identifying active services.

```php
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
}
```

## ServiceDetection\Repository
Handles loading the mapping from the filesystem.

```php
namespace EnvForm\ServiceDetection;

interface RepositoryInterface
{
    /**
     * Get the complete service mapping.
     */
    public function getMap(): array;
}
```
