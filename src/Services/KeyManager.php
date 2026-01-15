<?php

declare(strict_types=1);

namespace EnvForm\Services;

use EnvForm\DTO\EnvKeyDefinition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

final class KeyManager implements \EnvForm\Contracts\FormValueProvider
{
    /**
     * All keys found in config/*.php
     *
     * @var Collection<int, EnvKeyDefinition>
     */
    private Collection $configEnvKeys;

    /**
     * @var Collection<int, string>
     */
    private Collection $foundConfigFileNames;

    /**
     * @var Collection<int, object{key: string, value: string}>
     */
    private Collection $dotEnvKeyValuePairs;

    /**
     * All form values with format [ENV_KEY => VALUE]
     *
     * @var array<string, bool|int|string|null>
     */
    private array $formValues = [];

    final public function __construct(
        private readonly ConfigAnalyzer $analyzer,
        private readonly DotEnvService $dotEnvService
    ) {}

    /**
     * @return Collection<int, EnvKeyDefinition>
     */
    final public function getConfigEnvKeys(): Collection
    {
        if (! isset($this->configEnvKeys)) {
            $configEnvKeys = $this->analyzer->analyze();

            note(
                "✨ Found {$configEnvKeys->count()} potential environment variables to configure."
            );

            $this->configEnvKeys = $configEnvKeys;
            $this->foundConfigFileNames = $configEnvKeys->pluck('group')->unique();
        }

        return $this->configEnvKeys;
    }

    /**
     * @return Collection<int, string>
     */
    final public function getFoundConfigFileNames(): Collection
    {
        if (! isset($this->foundConfigFileNames)) {
            $this->getConfigEnvKeys();
        }

        return $this->foundConfigFileNames;
    }

    final public function getDefinitionByConfigKey(
        string $configKey
    ): ?EnvKeyDefinition {
        return $this->getConfigEnvKeys()
            ->firstWhere(
                'configKey',
                $configKey
            );
    }

    /**
     * @return Collection<int, object{key: string, value: string}>
     */
    private function getDotEnvKeyValuePairs(): Collection
    {
        if (! isset($this->dotEnvKeyValuePairs)) {
            $dotEnvFile = $this->selectEnvFile();

            // 2. Load Existing Env
            $dotEnvPath = App::basePath($dotEnvFile);

            if (file_exists($dotEnvPath)) {
                note("📖 Loading existing values from [{$dotEnvPath}]...");

                $this->dotEnvKeyValuePairs = $this->dotEnvService->read($dotEnvPath)
                    ->map(fn ($val, $key) => (object) ['key' => $key, 'value' => $val])
                    ->values();
            } else {
                note("🆕 File [{$dotEnvFile}] does not exist. Creating a new one.");

                $this->dotEnvKeyValuePairs = collect();
            }
        }

        return $this->dotEnvKeyValuePairs;
    }

    final public function getDotEnvValue(string $envKey): ?string
    {
        return $this->getDotEnvKeyValuePairs()
            ->firstWhere('key', $envKey)?->value;
    }

    final public function getCountDotEnvKeyValuePairs(): int
    {
        return $this->getDotEnvKeyValuePairs()->count();
    }

    private function selectEnvFile(): string
    {
        $options = $this->dotEnvService->findFiles(App::basePath());

        // Add option for new file
        $options['NEW'] = '➕ Create New File...';

        $choice = select(
            label: '📂 Which environment file do you want to manage?',
            options: $options,
            default: '.env'
        );

        if ($choice === 'NEW') {
            return text(
                label: '🆕 Enter the name of the new environment file',
                default: '.env.local',
                hint: 'e.g. .env.testing, .env.staging'
            );
        }

        return (string) $choice;
    }

    /**
     * @return Collection<int, EnvKeyDefinition>
     */
    final public function getShouldAskEnvKeys(
        ?string $group = null,
    ): Collection {
        $resolver = new DependencyResolver($this);

        $allEnvKeys = $this->getConfigEnvKeys();

        if ($group) {
            $allEnvKeys = $allEnvKeys->filter(fn (EnvKeyDefinition $key) => $key->group === $group);
        }

        return $allEnvKeys
            ->filter(fn (EnvKeyDefinition $envDef) => $resolver
                ->shouldAsk($envDef)
            );
    }

    /**
     * Get all form values with format [ENV_KEY => VALUE]
     *
     * @return array<string, bool|int|string|null>
     */
    final public function getFormValues(): array
    {
        return $this->formValues;
    }

    final public function getFormValue(string $envKey): mixed
    {
        return $this->formValues[$envKey] ?? null;
    }

    final public function setFormValue(
        string $envKey,
        mixed $value
    ): void {
        $this->formValues[$envKey] = $value;
    }
}
