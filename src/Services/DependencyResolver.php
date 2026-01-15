<?php

declare(strict_types=1);

namespace EnvForm\Services;

use EnvForm\Contracts\FormValueProvider;
use EnvForm\DTO\EnvKeyDefinition;

final class DependencyResolver
{
    public function __construct(
        private readonly FormValueProvider $provider
    ) {}

    /**
     * Filter out any keys that shouldn't be asked for.
     */
    final public function shouldAsk(
        EnvKeyDefinition $envDef
    ): bool {
        $configKeys = $this->resolveConfigKeys($envDef);

        if (empty($configKeys)) {
            return true;
        }

        foreach ($configKeys as $configKey) {
            if (
                $this->isConfigActive(
                    (string) $configKey
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

    private function isConfigActive(string $configKey): bool
    {
        if (empty($configKey)) {
            return true;
        }

        $rules = DependencyRules::getRules();

        // 1. Check if an explicit rule allows this path
        if ($this->isExplicitlyAllowed($configKey, $rules)) {
            return true;
        }

        // 2. Check if an implicit rule denies this path (i.e. it falls under a governed area but wasn't allowed)
        if ($this->isImplicitlyDenied($configKey, $rules)) {
            return false;
        }

        // 3. Default allow
        return true;
    }

    /**
     * @param  array<string, array<string, array<int, string>>>  $rules
     */
    private function isExplicitlyAllowed(
        string $configKey,
        array $rules
    ): bool {
        foreach ($rules as $dependantConfigKey => $conditions) {
            $dependantEnvDef = $this->provider->getDefinitionByConfigKey($dependantConfigKey);

            if (! $dependantEnvDef) {
                continue;
            }

            $dependantFormValue = $this->provider->getFormValue($dependantEnvDef->key);

            if (! $dependantFormValue) {
                continue;
            }

            foreach ($conditions as $expectedValue => $patterns) {
                // If the path matches the pattern for this condition
                // AND the dependant value matches the expected value, it is allowed.
                if ($this->matchesPatterns($configKey, $patterns) && (string) $dependantFormValue === (string) $expectedValue) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, array<string, array<int, string>>>  $rules
     */
    private function isImplicitlyDenied(string $configKey, array $rules): bool
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
            $dependantEnvDef = $this->provider->getDefinitionByConfigKey($dependantConfigKey);

            if (! $dependantEnvDef) {
                continue;
            }

            $formValue = $this->provider->getFormValue($dependantEnvDef->key);

            if (! $formValue || empty($conditions[$formValue])) {
                continue;
            }

            $patterns = $conditions[$formValue];

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
