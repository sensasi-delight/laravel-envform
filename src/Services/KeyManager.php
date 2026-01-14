<?php

declare(strict_types=1);

namespace EnvForm\Services;

use EnvForm\DTO\EnvKeyDefinition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

final class KeyManager
{
    /**
     * All keys found in config/*.php
     *
     * @var Collection<int, EnvKeyDefinition>
     */
    private static Collection $configEnvKeys;

    /**
     * @var Collection<int, string>
     */
    private static Collection $foundConfigFileNames;

    /**
     * @return Collection<int, EnvKeyDefinition>
     */
    final public static function getConfigEnvKeys(): Collection
    {
        if (empty(self::$configEnvKeys)) {
            $analyzer = app(ConfigAnalyzer::class);

            $configEnvKeys = $analyzer->analyze();

            note(
                "✨ Found {$configEnvKeys->count()} potential environment variables to configure."
            );

            self::$configEnvKeys = $configEnvKeys;
            self::$foundConfigFileNames = $configEnvKeys->pluck('group')->unique();
        }

        return self::$configEnvKeys;
    }

    /**
     * @return Collection<int, string>
     */
    final public static function getFoundConfigFileNames(): Collection
    {
        if (empty(self::$foundConfigFileNames)) {
            self::getConfigEnvKeys();
        }

        return self::$foundConfigFileNames;
    }

    /**
     * All key-value pairs found in .env
     *
     * @var Collection<int, object{key: string, value: string}>
     */
    private static Collection $dotEnvKeyValuePairs;

    /**
     * @return Collection<int, object{key: string, value: string}>
     */
    final public static function getDotEnvKeyValuePairs(): Collection
    {
        if (empty(self::$dotEnvKeyValuePairs)) {
            $dotEnvFile = self::selectEnvFile();

            // 2. Load Existing Env
            $dotEnvPath = App::basePath($dotEnvFile);

            if (file_exists($dotEnvPath)) {
                note("📖 Loading existing values from [{$dotEnvPath}]...");

                $envReader = app(EnvReader::class);

                self::$dotEnvKeyValuePairs = $envReader->read($dotEnvPath);
            } else {
                note("🆕 File [{$dotEnvFile}] does not exist. Creating a new one.");

                self::$dotEnvKeyValuePairs = collect();
            }
        }

        return self::$dotEnvKeyValuePairs;
    }

    private static function selectEnvFile(): string
    {
        $envFileHelper = app(EnvFileHelper::class);
        $options = $envFileHelper->findEnvFiles(App::basePath());

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
    final public static function getShouldAskEnvKeys(
        ?string $group = null,
    ): Collection {
        $resolver = new DependencyResolver;

        $allEnvKeys = self::getConfigEnvKeys();

        if ($group) {
            $allEnvKeys = $allEnvKeys->filter(fn (EnvKeyDefinition $key) => $key->group === $group);
        }

        return $allEnvKeys
            ->filter(fn (EnvKeyDefinition $key) => $resolver
                ->shouldAsk($key->key)
            );
    }

    /**
     * All form values with format [ENV_KEY => VALUE]
     *
     * @var array<string, bool|int|string|null>
     */
    private static $formValues = [];

    /**
     * Get all form values with format [ENV_KEY => VALUE]
     *
     * @return array<string, bool|int|string|null>
     */
    final public static function getFormValues(): array
    {
        return self::$formValues;
    }

    final public static function getFormValue(string $envKey): mixed
    {
        return self::$formValues[$envKey] ?? null;
    }

    final public static function setFormValue(
        string $envKey,
        mixed $value
    ): void {
        self::$formValues[$envKey] = $value;
    }
}
