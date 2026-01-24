# Contracts: Service Detection

## ServiceDetection\ServiceDetectionInterface
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

    /**
     * Refresh the service detection context (clears internal cache).
     */
    public function refresh(): void;
}
```

## ValueResolver\ValueResolverInterface
Updated to support existence checks without falling back to config defaults.

```php
namespace EnvForm\ValueResolver;

interface ValueResolverInterface
{
    public function resolve(string $key): mixed;

    /**
     * Determine if a value is explicitly set in FormValue or DotEnv.
     * Used by ServiceDetection to avoid false positives from static config defaults.
     */
    public function has(string $key): bool;
}
```
