<?php

declare(strict_types=1);

namespace EnvForm\DotEnv;

use EnvForm\DTO\EnvVar;
use EnvForm\FormValue;
use EnvForm\Registry;
use EnvForm\ShouldAsk;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

/**
 * Orchestrator for environment file persistence and state merging.
 * Bridges the gap between the discovered registry, form value, and the physical .env file.
 */
class Service
{
    private string $targetFile = '.env';

    /** @var Collection<string, string> [ENV_KEY => value] */
    private ?Collection $existingValues = null;

    public function __construct(
        private readonly FormValue\Service $formValue,
        private readonly Registry\Service $registry,
        private readonly Repository $repository,
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

    public function getExistingValue(string $envKey): ?string
    {
        $this->ensureLoaded();

        return $this->existingValues?->get($envKey);
    }

    public function hasExistingValue(string $key): bool
    {
        $this->ensureLoaded();

        return $this->existingValues?->has($key) ?? false;
    }

    /**
     * @return Collection<string, string>
     */
    public function getExistingValues(): Collection
    {
        $this->ensureLoaded();

        /** @var Collection<string, string> $existing */
        $existing = $this->existingValues;

        return $existing;
    }

    public function getCount(): int
    {
        $this->ensureLoaded();

        /** @var Collection<string, string> $existing */
        $existing = $this->existingValues;

        return $existing->count();
    }

    private function ensureLoaded(): void
    {
        if ($this->existingValues !== null) {
            return;
        }

        $path = App::basePath($this->targetFile);
        $this->existingValues = $this->repository->read($path);
    }

    /**
     * @return array<string, bool|int|string|null> [ENV_KEY => value]
     */
    private function getFinalValues(): array
    {
        $final = [];
        foreach ($this->registry->all() as $var) {
            $key = $var->key;

            $final[$key] = $this->formValue->get($key)
                ?? $this->getExistingValue($key)
                ?? $var->default;
        }

        return $final;
    }

    public function save(ShouldAsk\Service $shouldAsk): void
    {
        $path = App::basePath($this->targetFile);

        $metadata = $this->registry->all()
            ->keyBy('key')
            ->map(fn (EnvVar $var) => (object) [
                'shouldAsk' => $shouldAsk->isVisible($var),
                'group' => $var->group,
            ])->toArray();

        $content = $this->formatter->format(
            $this->getFinalValues(),
            $metadata,
        );

        $this->repository->write(
            $path,
            $content
        );
    }

    /**
     * @return array<string, string>
     */
    public function getEnvFileOptions(
        ?string $basePath = null
    ): array {
        $basePath = $basePath ?: App::basePath();
        $files = $this->repository->findDotEnvFiles($basePath);
        $options = [];

        foreach ($files as $file) {
            $options[$file->getFilename()] = $file->getFilename();
        }

        return $options;
    }
}
