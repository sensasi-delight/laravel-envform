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
        // 1. Service Relevance Guard
        // A variable is relevant if AT LEAST ONE of its config keys belongs to an active service
        // or no service at all (generic).
        $hasRelevantKey = false;
        foreach ($envVar->configKeys as $configKey) {
            if ($this->serviceDetection->isKeyRelevant($configKey)) {
                $hasRelevantKey = true;
                break;
            }
        }

        if (! $hasRelevantKey) {
            return false;
        }

        // 2. Dependency Rules
        return $this->satisfiesDependencyRules($envVar);
    }

    /**
     * @throws \Exception
     */
    private function satisfiesDependencyRules(EnvVar $envVar): bool
    {
        $dependencyRules = $this->repository->getMap();
        $hasGoverningPatterns = false;

        foreach ($envVar->configKeys as $configKey) {
            foreach ($dependencyRules as $pattern => $triggerConfigKey) {
                if (fnmatch($pattern, $configKey)) {
                    $hasGoverningPatterns = true;

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
        }

        // If it matches some patterns but none were satisfied, it's hidden.
        // If it matches NO patterns, it's visible.
        return ! $hasGoverningPatterns;
    }
}
