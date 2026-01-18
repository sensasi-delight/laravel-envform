<?php

declare(strict_types=1);

namespace EnvForm\Registry;

use Illuminate\Support\Collection;

/**
 * Static analysis engine for Laravel configuration files.
 * Uses a hybrid approach (Regex + AST) to discover env() calls and map them to config paths.
 */
interface RepositoryContract
{
    /**
     * Scan config directory for env() calls.
     *
     * @return Collection<int, array{envKey: string, configKey: string, defaultValue: mixed, file: string}>
     */
    public function scan(): Collection;

    /**
     * @return array<string, array<string, array<int, string>>>
     */
    public function getDependencyMap(): array;
}
