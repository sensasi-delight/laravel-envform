<?php

declare(strict_types=1);

namespace EnvForm\Services;

use EnvForm\Contracts\InputProvider;
use EnvForm\DTO\EnvVar;

final class UserSession implements InputProvider
{
    /** @var array<string, mixed> */
    private array $inputs = [];

    public function __construct(
        private readonly EnvRegistry $registry
    ) {}

    public function set(string $key, mixed $value): void
    {
        $this->inputs[$key] = $value;
    }

    public function input(string $key): mixed
    {
        return $this->inputs[$key] ?? null;
    }

    /** @return array<string, mixed> */
    public function allInputs(): array
    {
        return $this->inputs;
    }

    public function getDefinitionByConfigKey(string $configKey): ?EnvVar
    {
        return $this->registry->find($configKey);
    }
}
