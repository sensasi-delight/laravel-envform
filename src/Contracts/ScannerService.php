<?php

declare(strict_types=1);

namespace EnvForm\Contracts;

use EnvForm\DTO\EnvVar;
use Illuminate\Support\Collection;

/**
 * Static analysis engine for Laravel configuration files.
 * Uses a hybrid approach (Regex + AST) to discover env() calls and map them to config paths.
 */
interface ScannerService
{
    /**
     * Scan config directory for env() calls.
     *
     * @return Collection<int, EnvVar>
     */
    public function scan(): Collection;
}
