<?php

declare(strict_types=1);

namespace EnvForm\ShouldAsk;

class Repository
{
    /**
     * @var array<string, string>|null
     */
    private ?array $map = null;

    /**
     * @return array<string, string>
     *
     * @throws \Exception
     */
    public function getMap(): array
    {
        if ($this->map === null) {
            $this->map = require __DIR__.'/../../resources/dependencies.php';
        }

        if ($this->map === null) {
            throw new \Exception('Could not load dependency map');
        }

        return $this->map;
    }
}
