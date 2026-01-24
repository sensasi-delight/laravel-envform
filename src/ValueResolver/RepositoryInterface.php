<?php

declare(strict_types=1);

namespace EnvForm\ValueResolver;

use Closure;

interface RepositoryInterface
{
    /**
     * @return array<string, Closure>
     */
    public function all(): array;

    public function find(string $configPath): ?Closure;
}
