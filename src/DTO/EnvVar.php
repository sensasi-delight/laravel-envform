<?php

declare(strict_types=1);

namespace EnvForm\DTO;

/**
 * Data Transfer Object representing a single environment variable.
 * Encapsulates metadata such as current value, default value, dependencies, etc.
 */
final readonly class EnvVar
{
    public function __construct(
        /**
         * Config key where the env key is defined.
         */
        public string $configKey,

        /**
         * Config keys where the env key is defined.
         *
         * @var string[]
         */
        public array $configKeys,

        /**
         * Current value is retrieved from the `App::config()` on load.
         * So the value is the latest value before the CLI session.
         */
        public bool|int|null|string $currentValue,

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

        public string $description,

        public string $file,

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
