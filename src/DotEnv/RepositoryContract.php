<?php

declare(strict_types=1);

namespace EnvForm\DotEnv;

use Illuminate\Support\Collection;

/**
 * Low-level repository for interacting with .env files.
 * Handles reading parsing raw file content and writing raw content to disk.
 */
interface RepositoryContract
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
     * Write raw content to the .env file.
     */
    public function write(string $path, string $content): void;
}
