<?php

declare(strict_types=1);

namespace EnvForm\ValueResolver;

use Closure;

class Repository implements RepositoryInterface
{
    public function __construct(
        /**
         * @var string[] $paths
         */
        private array $paths,
    ) {}

    public function all(): array
    {
        $allRules = [];

        foreach ($this->paths as $path) {
            $file = rtrim($path, '/').'/inferences.php';

            if (! is_file($file)) {
                continue;
            }

            $data = require $file;

            if (! \is_array($data)) {
                continue;
            }

            $allRules = array_merge($allRules, $data);
        }

        return $allRules;
    }

    public function find(string $configPath): ?Closure
    {
        $rules = $this->all();

        return $rules[$configPath] ?? null;
    }
}
