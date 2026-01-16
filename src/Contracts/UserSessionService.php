<?php

declare(strict_types=1);

namespace EnvForm\Contracts;

/**
 * Temporary state container for user inputs during the CLI session.
 * Tracks the values provided by the user before they are merged and persisted.
 */
interface UserSessionService
{
    /**
     * Get input value for the given environment key.
     */
    public function input(string $envKey): mixed;

    /**
     * Set input value for the given environment key.
     */
    public function set(string $envKey, mixed $value): void;
}
