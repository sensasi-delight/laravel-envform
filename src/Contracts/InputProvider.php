<?php

declare(strict_types=1);

namespace EnvForm\Contracts;

use EnvForm\DTO\EnvVar;

interface InputProvider
{
    /**
     * Get input value for the given environment key.
     */
    public function input(string $envKey): mixed;

    /**
     * Get environment key definition by config key.
     */
    public function getDefinitionByConfigKey(string $configKey): ?EnvVar;
}
