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
        private Repository $repository
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

        $this->visibleVariables = $this->resolveVisibleVariables();
    }

    final public function isVisible(EnvVar $envVar): bool
    {
        return $this->visibleVariables->contains(fn (EnvVar $v) => $v->key === $envVar->key);
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
        );
    }

    private function shouldBeAsked(EnvVar $envVar): bool
    {
        return $this->satisfiesDependencyRules($envVar);
    }

    /**
     * @throws \Exception
     */
    private function satisfiesDependencyRules(EnvVar $envVar): bool
    {
        $dependencyRules = $this->repository->getMap();

        $governingPattern = null;
        $activeValue = null;

        foreach ($envVar->configKeys as $configKey) {
            foreach ($dependencyRules as $pattern => $triggerConfigKey) {
                if (fnmatch($pattern, $configKey)) {
                    $governingPattern = $pattern;

                    $triggerEnvVar = $this->registry->find($triggerConfigKey);

                    if (! $triggerEnvVar) {
                        continue;
                    }

                    $activeValue = $this->formValue->get($triggerEnvVar->key)
                        ?? $this->existingValues->get($triggerEnvVar->key)
                        ?? $this->registry->getStaticValue($triggerConfigKey);

                    break 2;
                }
            }
        }

        if (! $governingPattern) {
            return true;
        }

        if (! $activeValue) {
            return false;
        }

        $requiredConfigPattern = str_replace(
            '*',
            (string) $activeValue.'.*',
            $governingPattern
        );

        return $envVar->configKeys->contains(
            fn (string $configKey) => fnmatch($requiredConfigPattern, $configKey)
        );
    }
}
