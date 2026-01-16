<?php

declare(strict_types=1);

namespace EnvForm\Contracts;

use Illuminate\Support\Collection;

/**
 * Low-level service for interacting with .env files.
 * Handles parsing raw file content and writing structured, commented environment configurations.
 *
 * @phpstan-type EnvValue bool|int|string|null
 */
interface EnvFileService
{
    /**
     * Find available .env files in the base path.
     *
     * @return array<string, string>
     */
    public function findFiles(string $basePath): array;

    /**
     * Read and parse a .env file into a simple key-value collection.
     *
     * @return Collection<string, string>
     */
    public function read(string $path): Collection;

    /**
     * Update or create a .env file with given values and metadata.
     *
     * @param  array<string, EnvValue>  $values
     * @param  array<string, string>  $metadata  [ENV_KEY => GroupName]
     */
    public function write(string $path, array $values, array $metadata = []): void;
}
