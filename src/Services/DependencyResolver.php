<?php

declare(strict_types=1);

namespace EnvForm\Services;

use EnvForm\DTO\EnvKeyDefinition;

final class DependencyResolver
{
    /**
     * Filter out any keys that shouldn't be asked for.
     *
     * @param  array<string, mixed>  $currentValues
     */
    final public function shouldAsk(
        string $envKey,
        array $currentValues
    ): bool {

        $item = KeyManager::getConfigEnvKeys()->firstWhere('key', $envKey);

        if (! $item) {
            return true;
        }

        $configKeys = $this->resolveConfigKeys($item);

        if (empty($configKeys)) {
            return true;
        }

        foreach ($configKeys as $configKey) {
            if (
                $this->isConfigActive(
                    (string) $configKey,
                    $currentValues
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the given Env Key is a trigger for other dependencies.
     */
    public function isTrigger(string $envKey): bool
    {
        $allEnvKeys = KeyManager::getConfigEnvKeys();
        $item = $allEnvKeys->firstWhere('key', $envKey);
        if (! $item) {
            return false;
        }

        $paths = $this->resolveConfigKeys($item);
        $rules = DependencyRules::getRules();

        foreach ($paths as $path) {
            if (array_key_exists($path, $rules)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $currentValues
     */
    private function isConfigActive(string $configKey, array $currentValues): bool
    {
        if (empty($configKey)) {
            return true;
        }

        $rules = DependencyRules::getRules();

        // 1. Check if an explicit rule allows this path
        if ($this->isExplicitlyAllowed($configKey, $rules, $currentValues)) {
            return true;
        }

        // 2. Check if an implicit rule denies this path (i.e. it falls under a governed area but wasn't allowed)
        if ($this->isImplicitlyDenied($configKey, $rules, $currentValues)) {
            return false;
        }

        // 3. Default allow
        return true;
    }

    /**
     * @param  array<string, array<string, array<int, string>>>  $rules
     * @param  array<string, mixed>  $currentValues
     */
    private function isExplicitlyAllowed(
        string $configKey,
        array $rules,
        array $currentValues
    ): bool {
        foreach ($rules as $triggerConfigKey => $conditions) {
            $triggerItem = KeyManager::getConfigEnvKeys()->firstWhere('configKey', $triggerConfigKey);

            if (! $triggerItem) {
                continue;
            }

            $triggerKey = $triggerItem->key;
            $triggerValue = $currentValues[$triggerKey] ?? null;

            if (! $triggerValue) {
                continue;
            }

            foreach ($conditions as $expectedValue => $patterns) {
                // If the path matches the pattern for this condition
                // AND the trigger value matches the expected value, it is allowed.
                if ($this->matchesPatterns($configKey, $patterns) && (string) $triggerValue === (string) $expectedValue) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, array<string, array<int, string>>>  $rules
     * @param  array<string, mixed>  $collectedValues
     */
    private function isImplicitlyDenied(string $configKey, array $rules, array $collectedValues): bool
    {
        foreach ($rules as $conditions) {
            foreach ($conditions as $patterns) {
                if ($this->matchesPatterns($configKey, $patterns)) {
                    // It matches a restrictive pattern, but wasn't explicitly allowed above.
                    // Therefore it is hidden/denied.
                    return true;
                }
            }
        }

        foreach ($rules as $dependantConfigKey => $conditions) {
            $dependantEnvKey = KeyManager::getConfigEnvKeys()->firstWhere('configKey', $dependantConfigKey)->key ?? null;

            if (! $dependantEnvKey) {
                continue;
            }

            $collectedDependantValue = $collectedValues[$dependantEnvKey] ?? null;

            if (! $collectedDependantValue || empty($conditions[$collectedDependantValue])) {
                continue;
            }

            $patterns = $conditions[$collectedDependantValue];

            if (! $this->matchesPatterns($configKey, $patterns)) {
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
