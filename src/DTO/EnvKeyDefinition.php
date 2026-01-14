<?php

declare(strict_types=1);

namespace EnvForm\DTO;

final class EnvKeyDefinition
{
    /**
     * @param  string[]  $configKeys
     */
    public function __construct(
        /**
         * Env key
         */
        public readonly string $key,

        /**
         * Default value found in config file
         */
        public readonly mixed $default,

        public readonly string $file,
        public readonly string $description,
        public readonly string $group,

        /**
         * Config key where the env key is defined.
         */
        public readonly string $configKey,
        public readonly mixed $currentValue,

        /**
         * Config keys where the env key is defined.
         */
        public readonly array $configKeys = [],
    ) {}
}
