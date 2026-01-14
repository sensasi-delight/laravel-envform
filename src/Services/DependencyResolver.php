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

        $paths = $this->resolveConfigPaths($item);

        if (empty($paths)) {
            return true;
        }

        // If ANY usage path is active, we ask for the key.
        foreach ($paths as $path) {
            if (
                $this->isPathActive(
                    (string) $path,
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

        $paths = $this->resolveConfigPaths($item);
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
    private function isPathActive(string $configPath, array $currentValues): bool
    {
        if (empty($configPath)) {
            return true;
        }

        $rules = DependencyRules::getRules();

        // 1. Check if an explicit rule allows this path
        if ($this->isExplicitlyAllowed($configPath, $rules, $currentValues)) {
            return true;
        }

        // 2. Check if an implicit rule denies this path (i.e. it falls under a governed area but wasn't allowed)
        if ($this->isImplicitlyDenied($configPath, $rules, $currentValues)) {
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
        string $configPath,
        array $rules,
        array $currentValues
    ): bool {
        foreach ($rules as $triggerPath => $conditions) {
            $triggerItem = KeyManager::getConfigEnvKeys()->firstWhere('configPath', $triggerPath);

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
                if ($this->matchesPatterns($configPath, $patterns) && (string) $triggerValue === (string) $expectedValue) {
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
    private function isImplicitlyDenied(string $configPath, array $rules, array $collectedValues): bool
    {
        foreach ($rules as $conditions) {
            foreach ($conditions as $patterns) {
                if ($this->matchesPatterns($configPath, $patterns)) {
                    // It matches a restrictive pattern, but wasn't explicitly allowed above.
                    // Therefore it is hidden/denied.
                    return true;
                }
            }
        }

        foreach ($rules as $dependantConfigPath => $conditions) {
            $dependantEnvKey = KeyManager::getConfigEnvKeys()->firstWhere('configPath', $dependantConfigPath)->key ?? null;

            if (! $dependantEnvKey) {
                continue;
            }

            $collectedDependantValue = $collectedValues[$dependantEnvKey] ?? null;

            if (! $collectedDependantValue || empty($conditions[$collectedDependantValue])) {
                continue;
            }

            $patterns = $conditions[$collectedDependantValue];

            if (! $this->matchesPatterns($configPath, $patterns)) {
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
    private function resolveConfigPaths(EnvKeyDefinition $item): array
    {
        $paths = $item->configPaths;

        // Fallback to legacy single configPath if empty, though DTO should handle this.
        if (empty($paths) && ! empty($item->configPath)) {
            $paths = [$item->configPath];
        }

        return array_filter($paths);
    }
}
