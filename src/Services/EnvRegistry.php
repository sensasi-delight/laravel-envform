<?php

declare(strict_types=1);

namespace EnvForm\Services;

use EnvForm\DTO\EnvVar;
use Illuminate\Support\Collection;

/**
 * Central catalog of all environment variables discovered in the application.
 * Provides a read-only, structured view of the application's environment requirements.
 */
final class EnvRegistry
{
    /** @var Collection<int, EnvVar> */
    private Collection $vars;

    public function __construct(
        private readonly Scanner $scanner
    ) {
        $this->vars = $this->scanner->scan();
    }

    /** @return Collection<int, EnvVar> */
    public function all(): Collection
    {
        return $this->vars;
    }

    public function find(string $configKey): ?EnvVar
    {
        return $this->vars->firstWhere('configKey', $configKey);
    }

    /** @return Collection<int, string> */
    public function groups(): Collection
    {
        return $this->vars->pluck('group')->unique();
    }
}
