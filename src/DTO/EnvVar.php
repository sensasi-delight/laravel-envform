<?php

declare(strict_types=1);

namespace EnvForm\DTO;

use Illuminate\Support\Collection;

/**
 * Data Transfer Object representing a single environment variable.
 * Encapsulates metadata such as current value, default value, dependencies, etc.
 */
final readonly class EnvVar
{
    final public function __construct(
        /**
         * Config keys where the env key is defined.
         *
         * one ENV_KEY can be defined in multiple config files.
         *
         * @var Collection<int, string>
         */
        public Collection $configKeys,

        /**
         * Default value found declared in config file.
         */
        public bool|int|null|string $default,

        /**
         * Dependencies for this key.
         * Structure: ['triggerConfigKey' => ['value' => ['patterns']]]
         *
         * @var array<string, array<string, string[]>>
         */
        public array $dependencies,

        public string $group,

        /**
         * Whether this key triggers changes in other keys.
         */
        public bool $isTrigger,

        /**
         * Env key
         */
        public string $key,
    ) {}
}
