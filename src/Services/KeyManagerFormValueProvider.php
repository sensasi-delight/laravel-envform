<?php

declare(strict_types=1);

namespace EnvForm\Services;

use EnvForm\Contracts\FormValueProvider;
use EnvForm\DTO\EnvKeyDefinition;

final class KeyManagerFormValueProvider implements FormValueProvider
{
    public function getFormValue(string $envKey): mixed
    {
        return KeyManager::getFormValue($envKey);
    }

    public function getDefinitionByConfigKey(string $configKey): ?EnvKeyDefinition
    {
        return KeyManager::getDefinitionByConfigKey($configKey);
    }
}
