<?php

declare(strict_types=1);

namespace EnvForm\DotEnv;

use EnvForm\FormValue;
use EnvForm\Registry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

/**
 * Orchestrator for environment file persistence and state merging.
 * Bridges the gap between the discovered registry, form value, and the physical .env file.
 */
final class Service
{
    private string $targetFile = '.env';

    /** @var Collection<string, string>|null */
    private ?Collection $existingValues = null;

    public function __construct(
        private readonly FormValue\Service $formValue,
        private readonly Registry\Service $registry,
        private readonly RepositoryContract $repository,
        private readonly Formatter $formatter
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
            $this->existingValues = $this->repository->read($path);
        }

        return $this->existingValues->get($key);
    }

    public function getCount(): int
    {
        return $this->existingValues ? $this->existingValues->count() : 0;
    }

    /** @return array<string, mixed> */
    public function getFinalValues(): array
    {
        $final = [];
        foreach ($this->registry->all() as $var) {
            $key = $var->key;

            if (($val = $this->formValue->get($key)) !== null) {
                $final[$key] = $val;

                continue;
            }

            if (($val = $this->getExistingValue($key)) !== null) {
                $final[$key] = $val;

                continue;
            }

            $final[$key] = $var->default;
        }

        return $final;
    }

    public function save(): void
    {
        $path = App::basePath($this->targetFile);

        // 1. Calculate Active Values
        $activeValues = $this->getFinalValues();

        // 2. Calculate Deprecated Values (Keys in existing file that are NOT in active values)
        // We need to re-read or use existingValues to ensure we have the full picture of the file on disk
        if ($this->existingValues === null) {
            $this->existingValues = $this->repository->read($path);
        }

        // Keys that exist on disk but are not in the current registry/active set
        $deprecatedValues = array_diff_key(
            $this->existingValues->toArray(),
            $activeValues
        );

        // 3. Prepare Metadata
        $metadata = $this->registry->all()->pluck('group', 'key')->toArray();

        // 4. Format Content
        $content = $this->formatter->format($activeValues, $deprecatedValues, $metadata);

        // 5. Write to Disk
        $this->repository->write($path, $content);
    }
}
