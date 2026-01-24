<?php

declare(strict_types=1);

namespace EnvForm\FormValue;

class Service
{
    /** @var array<string, bool|int|string> */
    private array $inputs = [];

    public function set(string $envKey, bool|int|string $value): void
    {
        $this->inputs[$envKey] = $value;
    }

    public function get(string $envKey): bool|int|string|null
    {
        return $this->inputs[$envKey] ?? null;
    }

    public function isDirty(): bool
    {
        return \count($this->inputs) > 0;
    }
}
