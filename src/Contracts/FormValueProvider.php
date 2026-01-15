<?php

declare(strict_types=1);

namespace EnvForm\Contracts;

use EnvForm\DTO\EnvKeyDefinition;

interface FormValueProvider
{
    /**
     * Get form value for the given environment key.
     */
    public function getFormValue(string $envKey): mixed;

    /**
     * Get environment key definition by config key.
     */
    public function getDefinitionByConfigKey(string $configKey): ?EnvKeyDefinition;
}
