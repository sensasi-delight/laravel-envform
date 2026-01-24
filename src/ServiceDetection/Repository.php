<?php

declare(strict_types=1);

namespace EnvForm\ServiceDetection;

use EnvForm\ServiceDetection\DTO\ServiceDefinition;

class Repository
{
    /**
     * @var array<string, ServiceDefinition>|null
     */
    private ?array $map = null;

    /**
     * @return array<string, ServiceDefinition>
     */
    public function getMap(): array
    {
        if ($this->map === null) {
            $rawMap = require __DIR__.'/../../resources/services.php';
            $this->map = [];

            foreach ($rawMap as $name => $data) {
                $this->map[$name] = new ServiceDefinition(
                    $name,
                    $data['activators'] ?? [],
                    $data['master_keys'] ?? [],
                    $data['patterns'] ?? []
                );
            }
        }

        return $this->map;
    }
}
