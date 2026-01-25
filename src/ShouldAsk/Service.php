<?php

declare(strict_types=1);

namespace EnvForm\ShouldAsk;

use EnvForm\DTO\EnvVar;
use EnvForm\FormValue;
use EnvForm\Registry;
use Illuminate\Support\Collection;

final class Service
{
    /**
     * Collection of environment variables that should be asked.
     *
     * @var Collection<int, EnvVar>
     */
    private Collection $visibleVariables;

    /** @var Collection<string, string> */
    private Collection $existingValues;

    final public function __construct(
        private FormValue\Service $formValue,
        private Registry\Service $registry,
        private Repository $repository,
        private \EnvForm\ServiceDetection\ServiceDetectionInterface $serviceDetection
    ) {
        $this->existingValues = collect();
        $this->refresh();
    }

    /**
     * @return Collection<int, EnvVar>
     */
    private function resolveVisibleVariables(): Collection
    {
        return $this->registry
            ->all()
            ->filter(
                fn (EnvVar $v) => $this->shouldBeAsked($v)
            );
    }

    /**
     * @param  Collection<string, string>|null  $existingValues
     */
    final public function refresh(?Collection $existingValues = null): void
    {
        if ($existingValues !== null) {
            $this->existingValues = $existingValues;
        }

        $this->serviceDetection->refresh();
        $this->visibleVariables = $this->resolveVisibleVariables();
    }

    final public function isVisible(EnvVar $envVar): bool
    {
        return $this->visibleVariables->contains(fn (EnvVar $v) => $v->key === $envVar->key);
    }

    /**
     * @return Collection<int, EnvVar>
     */
    final public function all(): Collection
    {
        return $this->visibleVariables;
    }

    final public function countVisible(?string $group = null): int
    {
        return $group
            ? $this->getVisibleVariablesByGroup($group)->count()
            : $this->visibleVariables->count();
    }

    /**
     * @return Collection<int, EnvVar>
     */
    final public function getVisibleVariablesByGroup(string $group): Collection
    {
        return $this->visibleVariables->filter(
            fn (EnvVar $v) => $v->group === $group
        )->sortBy(fn (EnvVar $v) => ($v->isTrigger ? 0 : 1).$v->key);
    }

    private function shouldBeAsked(EnvVar $envVar): bool
    {
        foreach ($envVar->configKeys as $configKey) {
            // 1. Check Service Relevance
            if (! $this->serviceDetection->isKeyRelevant($configKey)) {
                continue;
            }

            // 2. Check Dependency Rules for this specific relevant key
            if ($this->satisfiesDependencyRulesForKey($configKey)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    private function satisfiesDependencyRulesForKey(string $configKey): bool
    {
        $dependencyRules = $this->repository->getMap();
        $isGoverned = false;

        foreach ($dependencyRules as $pattern => $triggerConfigKey) {
            if (fnmatch($pattern, $configKey)) {
                $isGoverned = true;

                $triggerEnvVar = $this->registry->find($triggerConfigKey);
                if (! $triggerEnvVar) {
                    continue;
                }

                $activeValue = $this->formValue->get($triggerEnvVar->key)
                    ?? $this->existingValues->get($triggerEnvVar->key)
                    ?? $this->registry->getStaticValue($triggerConfigKey);

                if (! $activeValue) {
                    continue;
                }

                $requiredConfigPattern = str_replace(
                    '*',
                    (string) $activeValue.'.*',
                    $pattern
                );

                if (fnmatch($requiredConfigPattern, $configKey)) {
                    return true;
                }
            }
        }

        // If no dependency rules govern this key, it's satisfied by default.
        // If it IS governed but none of the rules matched above, it's NOT satisfied.
        return ! $isGoverned;
    }
}
