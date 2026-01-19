<?php

declare(strict_types=1);

namespace EnvForm\ShouldAsk;

interface RepositoryContract
{
    /**
     * @return array<string, string>
     */
    public function getMap(): array;
}
