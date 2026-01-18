<?php

declare(strict_types=1);

namespace EnvForm\Rules;

use EnvForm\DTO\EnvVar;
use EnvForm\FormValue;
use EnvForm\Registry;
use Illuminate\Support\Collection;

/**
 * Deterministic engine for evaluating dependency rules between environment variables.
 * Decides whether a variable should be prompted based on the state of its trigger variables.
 */
final readonly class Service
{
    public function __construct(
        private FormValue\Service $formValue,
        private Registry\Service $registry
    ) {}

    /**
     * Filter out any keys that shouldn't be asked for.
     */
    public function shouldAsk(EnvVar $envDef): bool
    {
        // If no dependencies, always ask
        if (empty($envDef->dependencies)) {
            return true;
        }

        foreach ($envDef->dependencies as $triggerConfigKey => $valueMap) {
            $triggerEnvKey = $this->registry->find($triggerConfigKey);

            if (! $triggerEnvKey) {
                continue;
            }

            $currentTriggerValue = (string) $this->formValue->get($triggerEnvKey->key);

            if (! $currentTriggerValue) {
                continue;
            }

            // Check if the current value of the trigger matches any condition for this key
            $patterns = $valueMap[$currentTriggerValue] ?? null;
            if ($patterns && $this->matchesAnyConfigKey($envDef->configKeys, $patterns)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, string>  $configKeys
     * @param  string[]  $patterns
     */
    private function matchesAnyConfigKey(Collection $configKeys, array $patterns): bool
    {
        foreach ($configKeys as $configKey) {
            if ($this->matchesPatterns($configKey, $patterns)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  string[]  $patterns
     */
    private function matchesPatterns(string $configKey, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $configKey)) {
                return true;
            }
        }

        return false;
    }
}
