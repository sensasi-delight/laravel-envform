<?php

declare(strict_types=1);

namespace EnvForm\Contracts;

/**
 * Terminal UI (TUI) orchestrator for the interactive configuration process.
 * Manages the prompt loop, progress display, and navigation between configuration groups.
 */
interface WizardService
{
    public function run(): void;
}
