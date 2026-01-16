<?php

declare(strict_types=1);

namespace EnvForm\Services;

use EnvForm\Contracts\EnvFileService;
use EnvForm\Contracts\UserSessionService;
use EnvForm\DTO\EnvVar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

/**
 * Orchestrator for environment file persistence and state merging.
 * Bridges the gap between the discovered registry, user session, and the physical .env file.
 */
final class EnvManager
{
    private string $targetFile = '.env';

    /** @var Collection<string, string>|null */
    private ?Collection $existingValues = null;

    public function __construct(
        private readonly EnvRegistry $registry,
        private readonly UserSessionService $session,
        private readonly EnvFileService $file
    ) {}

    public function setTargetFile(string $filename): void
    {
        $this->targetFile = $filename;
        $this->existingValues = null;
    }

    public function getTargetFile(): string
    {
        return $this->targetFile;
    }

    public function getExistingValue(string $key): ?string
    {
        if ($this->existingValues === null) {
            $path = App::basePath($this->targetFile);
            $this->existingValues = $this->file->read($path);
        }

        return $this->existingValues->get($key);
    }

    /** @return array{pending: int, existing: int} */
    public function getSummary(): array
    {
        return [
            'pending' => $this->getPendingCount(),
            'existing' => $this->existingValues ? $this->existingValues->count() : 0,
        ];
    }

    private function getPendingCount(): int
    {
        $engine = new RuleEngine(
            $this->session,
            $this->registry
        );

        return $this->registry->all()
            ->filter(fn (EnvVar $var) => $engine->shouldAsk($var))
            ->count();
    }

    /** @return array<string, mixed> */
    public function getFinalValues(): array
    {
        $final = [];
        foreach ($this->registry->all() as $var) {
            $key = $var->key;

            if (($val = $this->session->input($key)) !== null) {
                $final[$key] = $val;

                continue;
            }

            if (($val = $this->getExistingValue($key)) !== null) {
                $final[$key] = $val;

                continue;
            }

            $final[$key] = $var->currentValue ?? $var->default;
        }

        return $final;
    }

    public function save(): void
    {
        $path = App::basePath($this->targetFile);
        $values = $this->getFinalValues();
        $metadata = $this->registry->all()->pluck('group', 'key')->toArray();

        $this->file->write($path, $values, $metadata);
    }
}
