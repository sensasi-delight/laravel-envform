<?php

declare(strict_types=1);

namespace EnvForm\Hint;

final readonly class Repository implements RepositoryContract
{
    final public function __construct(
        /**
         * Will supported multiple files and paths
         * on the future for the hints if needed
         *
         * @var string[] $paths
         */
        private array $paths,
    ) {}

    final public function get(string $configKey): string
    {
        foreach ($this->paths as $path) {
            $file = rtrim($path, '/').'/hints.php';

            if (! is_file($file)) {
                continue;
            }

            $data = require $file;

            if (! \is_array($data)) {
                continue;
            }

            if (\array_key_exists($configKey, $data)) {
                return $data[$configKey];
            }
        }

        return '';
    }
}
