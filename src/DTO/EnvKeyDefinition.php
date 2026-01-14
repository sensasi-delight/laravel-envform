<?php

declare(strict_types=1);

namespace EnvForm\DTO;

final class EnvKeyDefinition
{
    /**
     * @param  string[]  $configPaths
     */
    public function __construct(
        public readonly string $key,
        public readonly mixed $default,
        public readonly string $file,
        public readonly string $description,
        public readonly string $group,
        public readonly string $configPath,
        public readonly array $configPaths = [],
        public readonly mixed $currentValue,
    ) {}
}
