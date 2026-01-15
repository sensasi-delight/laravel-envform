<?php

declare(strict_types=1);

namespace EnvForm\Services;

use EnvForm\DTO\EnvKeyDefinition;

final class DependencyResolver
{
    public function __construct(
        private readonly KeyManager $keyManager
    ) {}

    /**
     * Filter out any keys that shouldn't be asked for.
     */
    final public function shouldAsk(
        EnvKeyDefinition $envDef
    ): bool {
        $rules = DependencyRules::getRules();

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

                $isDependantValueRegistered = \array_key_exists(
                    $collectedDependantValue,
                    $conditions
                );

                if (! $isDependantValueRegistered) {
                    continue;
                }

                if ($this->matchesPatterns(
                    $configKey,
                    $conditions[$collectedDependantValue]
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
        $rules = DependencyRules::getRules();

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
