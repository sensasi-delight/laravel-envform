<?php

declare(strict_types=1);

namespace EnvForm\Services;

use EnvForm\Contracts\UserSessionService;

final class UserSession implements UserSessionService
{
    /** @var array<string, mixed> */
    private array $inputs = [];

    public function set(string $key, mixed $value): void
    {
        $this->inputs[$key] = $value;
    }

    public function input(string $key): mixed
    {
        return $this->inputs[$key] ?? null;
    }
}
