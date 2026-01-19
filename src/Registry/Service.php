<?php

declare(strict_types=1);

namespace EnvForm\Registry;

use EnvForm\DTO\EnvVar;
use Illuminate\Support\Collection;

final readonly class Service
{
    /** @var Collection<int, EnvVar> */
    private Collection $vars;

    /**
     * @throws \Exception
     */
    public function __construct(
        private readonly RepositoryContract $repository,
    ) {
        $rawFindings = $this->repository->scan();
        $dependencyMap = $this->repository->getDependencyMap();

        $this->vars = $rawFindings->groupBy('envKey')
            ->map(function (
                Collection $occurrences,
                string $envKey
            ) use ($dependencyMap): EnvVar {
                $configKeys = $occurrences->pluck('configKey');
                $firstOccurrence = $occurrences->first();

                if ($firstOccurrence === null) {
                    throw new \Exception("Could not find any occurrences for {$envKey}", 1);
                }

                return new EnvVar(
                    $configKeys,
                    $firstOccurrence['defaultValue'],
                    $firstOccurrence['file'], // Group by file
                    $configKeys->contains(fn (
                        string $configKey
                    ) => \in_array(
                        $configKey,
                        $dependencyMap
                    )),
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
