<?php

declare(strict_types=1);

namespace EnvForm\Registry;

use EnvForm\DTO\EnvVar;
use EnvForm\Services\RuleEngine;
use Illuminate\Support\Collection;

final readonly class Service
{
    /** @var Collection<int, EnvVar> */
    private Collection $vars;

    /**
     * @throws \Exception
     */
    public function __construct(
        private readonly RepositoryContract $repository
    ) {
        $rawFindings = $this->repository->scan();

        $this->vars = $rawFindings->groupBy('envKey')
            ->map(function (Collection $occurrences, string $envKey) {
                $configKeys = $occurrences->pluck('configKey');
                $firstOccurrence = $occurrences->first();

                if ($firstOccurrence === null) {
                    throw new \Exception("Could not find any occurrences for {$envKey}", 1);
                }

                // Calculate dependencies based on RuleEngine
                $dependencies = [];
                foreach (RuleEngine::RULES as $triggerKey => $conditions) {
                    foreach ($conditions as $triggerValue => $patterns) {
                        foreach ($configKeys as $ck) {
                            foreach ($patterns as $pattern) {
                                if (fnmatch($pattern, $ck)) {
                                    $dependencies[$triggerKey][$triggerValue] = $patterns;
                                    break;
                                }
                            }
                        }
                    }
                }

                $isTrigger = $configKeys->contains(fn (string $configKey) => \array_key_exists($configKey, RuleEngine::RULES));

                return new EnvVar(
                    $configKeys,
                    $firstOccurrence['defaultValue'],
                    $dependencies,
                    $firstOccurrence['file'],
                    $firstOccurrence['file'], // Group by file
                    $isTrigger,
                    $envKey,
                );
            })->sortBy('key')->values();
    }

    /**
     * @return Collection<int, EnvVar>
     */
    public function all(): Collection
    {
        return $this->vars;
    }

    public function find(string $configKey): ?EnvVar
    {
        return $this->vars->firstWhere(fn ($var) => $var->configKeys->contains($configKey));
    }

    /** @return Collection<int, string> */
    public function groups(): Collection
    {
        return $this->vars->pluck('group')->unique();
    }
}
