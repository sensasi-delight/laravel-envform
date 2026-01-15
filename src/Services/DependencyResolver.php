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
        $matchedAny = false;

        foreach ($rules as $dependantKey => $conditions) {
            foreach ($conditions as $expectedValue => $patterns) {
                if ($this->matchesPatterns($configKey, $patterns)) {
                    $matchedAny = true;
                    $def = $this->provider->getDefinitionByConfigKey($dependantKey);
                    $val = $def ? $this->provider->getFormValue($def->key) : null;

                    if ((string) $val === (string) $expectedValue) {
                        return true;
                    }
                }
            }
        }

        return ! $matchedAny;
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
