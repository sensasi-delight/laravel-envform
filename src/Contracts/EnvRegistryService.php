<?php

declare(strict_types=1);

namespace EnvForm\Contracts;

use EnvForm\DTO\EnvVar;
use Illuminate\Support\Collection;

/**
 * Central catalog of all environment variables discovered in the application.
 * Provides a read-only, structured view of the application's environment requirements.
 */
interface EnvRegistryService
{
    /**
     * @return Collection<int, EnvVar>
     */
    public function all(): Collection;

    public function find(string $configKey): ?EnvVar;

    /**
     * @return Collection<int, string>
     */
    public function groups(): Collection;
}
