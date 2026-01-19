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
final class Service
{
    private string $targetFile = '.env';

    /** @var Collection<string, string>|null */
    private ?Collection $existingValues = null;

    final public function __construct(
        private readonly FormValue\Service $formValue,
        private readonly Registry\Service $registry,
        private readonly ShouldAsk\Service $shouldAsk,
        private readonly Repository $repository,
        private readonly Formatter $formatter
    ) {}

    final public function setTargetFile(string $filename): void
    {
        $this->targetFile = $filename;
        $this->existingValues = null;
    }

    final public function getTargetFile(): string
    {
        return $this->targetFile;
    }

    final public function getExistingValue(string $key): ?string
    {
        if ($this->existingValues === null) {
            $path = App::basePath($this->targetFile);
            $this->existingValues = $this->repository->read($path);
        }

        return $this->existingValues->get($key);
    }

    final public function getCount(): int
    {
        return $this->existingValues ? $this->existingValues->count() : 0;
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

    public function save(): void
    {
        $path = App::basePath($this->targetFile);

        $metadata = $this->registry->all()
            ->keyBy('key')
            ->map(fn (EnvVar $var) => (object) [
                'shouldAsk' => $this->shouldAsk->isVisible($var),
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
    final public function getEnvFileOptions(
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
