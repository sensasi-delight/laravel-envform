<?php

declare(strict_types=1);

namespace EnvForm\Services;

use EnvForm\DTO\EnvVar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

use function Laravel\Prompts\note;

class EnvironmentState implements \EnvForm\Contracts\InputProvider
{
    /**
     * All keys found in config/*.php
     *
     * @var Collection<int, EnvVar>
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

    private string $targetEnvFile = '.env';

    /**
     * All form values with format [ENV_KEY => VALUE]
     *
     * @var array<string, bool|int|string|null>
     */
    private array $formValues = [];

    public function __construct(
        private readonly Scanner $scanner,
        private readonly EnvFile $envFile
    ) {}

    /**
     * @return Collection<int, EnvVar>
     */
    public function all(): Collection
    {
        if (! isset($this->configEnvKeys)) {
            $configEnvKeys = $this->scanner->scan();

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
    public function getFoundConfigFileNames(): Collection
    {
        if (! isset($this->foundConfigFileNames)) {
            $this->all();
        }

        return $this->foundConfigFileNames;
    }

    public function getDefinitionByConfigKey(
        string $configKey
    ): ?EnvVar {
        return $this->all()
            ->firstWhere(
                'configKey',
                $configKey
            );
    }

    public function setTargetEnvFile(string $filename): void
    {
        $this->targetEnvFile = $filename;
        unset($this->dotEnvKeyValuePairs);
    }

    /**
     * @return Collection<int, object{key: string, value: string}>
     */
    private function getDotEnvKeyValuePairs(): Collection
    {
        if (! isset($this->dotEnvKeyValuePairs)) {
            $dotEnvPath = App::basePath($this->targetEnvFile);

            if (file_exists($dotEnvPath)) {
                note("📖 Loading existing values from [{$dotEnvPath}]...");

                $this->dotEnvKeyValuePairs = $this->envFile->read($dotEnvPath)
                    ->map(fn ($val, $key) => (object) ['key' => $key, 'value' => $val])
                    ->values();
            } else {
                note("🆕 File [{$this->targetEnvFile}] does not exist. Creating a new one.");

                $this->dotEnvKeyValuePairs = collect();
            }
        }

        return $this->dotEnvKeyValuePairs;
    }

    public function getDotEnvValue(string $envKey): ?string
    {
        return $this->getDotEnvKeyValuePairs()
            ->firstWhere('key', $envKey)?->value;
    }

    public function getCountDotEnvKeyValuePairs(): int
    {
        return $this->getDotEnvKeyValuePairs()->count();
    }

    /**
     * @return Collection<int, EnvVar>
     */
    public function pending(
        ?string $group = null,
    ): Collection {
        $ruleEngine = new RuleEngine($this);

        $allEnvKeys = $this->all();

        if ($group) {
            $allEnvKeys = $allEnvKeys->filter(fn (EnvVar $key) => $key->group === $group);
        }

        return $allEnvKeys
            ->filter(fn (EnvVar $envDef) => $ruleEngine
                ->shouldAsk($envDef)
            );
    }

    /**
     * Get all form values with format [ENV_KEY => VALUE]
     *
     * @return array<string, bool|int|string|null>
     */
    public function getFormValues(): array
    {
        return $this->formValues;
    }

    public function input(string $envKey): bool|int|string|null
    {
        return $this->formValues[$envKey] ?? null;
    }

    public function setFormValue(
        string $envKey,
        mixed $value
    ): void {
        $this->formValues[$envKey] = $value;
    }

    /**
     * Get the final list of values to write to .env, merging:
     * 1. Form Values (User input)
     * 2. Existing .env values
     * 3. Current Config Values / Defaults
     *
     * @return array<string, bool|int|string|null>
     */
    public function getFinalValues(): array
    {
        $finalValues = [];

        foreach ($this->all() as $definition) {
            $key = $definition->key;

            // Priority 1: User Input
            if (\array_key_exists($key, $this->formValues)) {
                $finalValues[$key] = $this->formValues[$key];

                continue;
            }

            // Priority 2: Existing .env Value
            $existing = $this->getDotEnvValue($key);
            if ($existing !== null) {
                $finalValues[$key] = $existing;

                continue;
            }

            // Priority 3: Current Config Value (from application runtime)
            // Priority 4: Default Value (from env() call signature)
            $finalValues[$key] = $definition->currentValue ?? $definition->default;
        }

        return $finalValues;
    }
}
