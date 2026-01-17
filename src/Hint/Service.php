<?php

declare(strict_types=1);

namespace EnvForm\Hint;

final readonly class Service
{
    public function __construct(
        private readonly RepositoryContract $repository,
    ) {}

    /**
     * Retrieve the hint for the given config key.
     *
     * @param  string  $configKey  Config key to retrieve the hint for.
     * @return string Hint associated with the config key, or null if not found.
     */
    public function get(string $configKey): string
    {
        return $this->repository->get($configKey);
    }
}
