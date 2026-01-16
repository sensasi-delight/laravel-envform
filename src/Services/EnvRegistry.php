<?php

declare(strict_types=1);

namespace EnvForm\Services;

use EnvForm\Contracts\EnvRegistryService;
use EnvForm\DTO\EnvVar;
use Illuminate\Support\Collection;

final class EnvRegistry implements EnvRegistryService
{
    /** @var Collection<int, EnvVar> */
    private Collection $vars;

    public function __construct(
        private readonly Scanner $scanner
    ) {
        $this->vars = $this->scanner->scan();
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
        return $this->vars->firstWhere('configKey', $configKey);
    }

    /** @return Collection<int, string> */
    public function groups(): Collection
    {
        return $this->vars->pluck('group')->unique();
    }
}
