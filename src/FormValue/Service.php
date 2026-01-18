<?php

declare(strict_types=1);

namespace EnvForm\FormValue;

final class Service
{
    /** @var array<string, bool|int|string> */
    private array $inputs = [];

    final public function set(string $envKey, bool|int|string $value): void
    {
        $this->inputs[$envKey] = $value;
    }

    final public function get(string $envKey): bool|int|string|null
    {
        return $this->inputs[$envKey] ?? null;
    }

    final public function isDirty(): bool
    {
        return \count($this->inputs) > 0;
    }

    final public function clear(): void
    {
        $this->inputs = [];
    }
}
