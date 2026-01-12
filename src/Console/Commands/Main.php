<?php

declare(strict_types=1);

namespace EnvForm\Console\Commands;

use EnvForm\Services\ConfigAnalyzer;
use EnvForm\Services\EnvWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Symfony\Component\Finder\Finder;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

final class Main extends Command
{
    /**
     * @var string
     */
    protected $signature = 'envform';

    /**
     * @var string
     */
    protected $description = 'Interactively manage .env file based on project analysis.';

    /** @var array<string, string> */
    private array $existingEnv = [];

    /** @var array<string, mixed> */
    private array $collectedValues = [];

    private string $targetEnvFile = '.env';

    final public function handle(ConfigAnalyzer $analyzer): int
    {
        $this->displayWelcome();

        // 1. Select Target Environment File
        $this->targetEnvFile = $this->selectEnvFile();

        $configPath = App::configPath();
        $this->info("🔍 Analyzing project configuration in: {$configPath}...");

        $allKeys = $analyzer->analyze($configPath);

        if ($allKeys->isEmpty()) {
            warning('⚠️  No env() calls found in config/*.php. Please check your configuration files.');

            return self::FAILURE;
        }

        note("✨ Found {$allKeys->count()} potential environment variables to configure.");

        // 2. Load Existing Env
        $envPath = App::basePath($this->targetEnvFile);
        if (file_exists($envPath)) {
            note("📖 Loading existing values from [{$this->targetEnvFile}]...");
            $this->existingEnv = $this->getCurrentEnv($envPath);
        } else {
            note("🆕 File [{$this->targetEnvFile}] does not exist. Creating a new one.");
            $this->existingEnv = [];
        }

        $groupedKeys = $allKeys->groupBy('group')->sortKeys();

        $this->runInteractiveLoop($groupedKeys);

        return $this->saveChanges();
    }

    private function displayWelcome(): void
    {
        info('🚀 Welcome to Laravel EnvForm!');
        note('💡 LOCAL ANALYSIS: This tool scans your config directory locally and writes directly to your .env file.');
        note('🔒 PRIVACY: No data is sent to external servers. All processing stays on your machine.');
    }

    private function selectEnvFile(): string
    {
        $files = Finder::create()
            ->files()
            ->in(App::basePath())
            ->name('.env*')
            ->depth(0)
            ->ignoreDotFiles(false);

        $options = [];
        foreach ($files as $file) {
            $options[$file->getFilename()] = $file->getFilename();
        }

        // Add option for new file
        $options['NEW'] = '➕ Create New File...';

        $choice = select(
            label: '📂 Which environment file do you want to manage?',
            options: $options,
            default: '.env'
        );

        if ($choice === 'NEW') {
            return text(
                label: '🆕 Enter the name of the new environment file:',
                default: '.env.local',
                hint: 'e.g. .env.testing, .env.staging'
            );
        }

        return (string) $choice;
    }

    /**
     * @param  Collection<string, Collection<int, array{key: string, default: mixed, file: string, description: string, group: string}>>  $groupedKeys
     */
    private function runInteractiveLoop(Collection $groupedKeys): void
    {
        while (true) {
            $menuOptions = $this->buildMenuOptions($groupedKeys);

            $selectedGroup = select(
                label: '📂 Select a configuration file to configure:',
                options: $menuOptions,
                default: 'EXIT',
                scroll: 15
            );

            if ($selectedGroup === 'EXIT') {
                break;
            }

            /** @var Collection<int, array{key: string, default: mixed, file: string, description: string, group: string}> $keys */
            $keys = $groupedKeys[$selectedGroup];
            $this->configureGroup((string) $selectedGroup, $keys);
        }
    }

    /**
     * @param  Collection<string, Collection<int, array{key: string, default: mixed, file: string, description: string, group: string}>>  $groupedKeys
     * @return array<string, string>
     */
    private function buildMenuOptions(Collection $groupedKeys): array
    {
        $menuOptions = [];
        foreach ($groupedKeys as $groupName => $keys) {
            /** @var string $groupName */
            $total = $keys->count();
            $filled = $keys->filter(function ($item) {
                $key = $item['key'];
                $val = $this->collectedValues[$key] ?? $this->existingEnv[$key] ?? null;

                return ! empty($val) || $val === '0' || $val === false;
            })->count();

            $status = ($filled >= $total) ? '✅' : "({$filled}/{$total})";
            $menuOptions[$groupName] = "{$groupName} {$status}";
        }

        $menuOptions['EXIT'] = '💾 Save & Exit';

        return $menuOptions;
    }

    /**
     * @param  Collection<int, array{key: string, default: mixed, file: string, description: string, group: string}>  $keys
     */
    private function configureGroup(string $groupName, Collection $keys): void
    {
        info("🛠️  Configuring settings for: {$groupName}");

        foreach ($keys as $meta) {
            /** @var array{key: string, description: string, default: mixed} $meta */
            $this->askForValue($meta);
        }
    }

    /**
     * @param  array{key: string, description: string, default: mixed}  $meta
     */
    private function askForValue(array $meta): void
    {
        $keyName = $meta['key'];

        // Skip if special handling took care of it
        if ($this->handleSpecialKeys($keyName, $meta)) {
            return;
        }

        $currentValue = $this->collectedValues[$keyName] ?? $this->existingEnv[$keyName] ?? null;
        $defaultValue = $meta['default'];

        $label = "👉 {$keyName}";
        $hint = $meta['description'];

        if ($defaultValue !== null) {
            $displayDefault = is_bool($defaultValue) ? ($defaultValue ? 'true' : 'false') : (string) $defaultValue;
            $hint .= " (Default: {$displayDefault})";
        }

        $initial = $currentValue ?? $defaultValue;

        if (is_bool($defaultValue)) {
            $boolInitial = $initial;
            if (is_string($initial)) {
                $boolInitial = strtolower($initial) === 'true';
            }

            $this->collectedValues[$keyName] = confirm(
                label: $label,
                default: (bool) $boolInitial,
                hint: $hint
            );

            return;
        }

        $this->collectedValues[$keyName] = text(
            label: $label,
            default: (string) $initial,
            hint: $hint
        );
    }

    /**
     * @param  array{default: mixed, description: string}  $meta
     */
    private function handleSpecialKeys(string $keyName, array $meta): bool
    {
        if ($keyName === 'APP_KEY') {
            $currentValue = $this->collectedValues[$keyName] ?? $this->existingEnv[$keyName] ?? null;
            if (confirm('🔑 Do you want to generate/regenerate APP_KEY?', default: empty($currentValue))) {
                Artisan::call('key:generate', ['--show' => true]);
                $this->collectedValues[$keyName] = trim(Artisan::output());

                return true;
            }

            return false;
        }

        $connectionConfigMap = [
            'DB_CONNECTION' => 'database.connections',
            'QUEUE_CONNECTION' => 'queue.connections',
            'BROADCAST_CONNECTION' => 'broadcasting.connections',
        ];

        if (array_key_exists($keyName, $connectionConfigMap)) {
            /** @var array<int, string> $connections */
            $connections = array_keys(Config::get($connectionConfigMap[$keyName], []));

            if (! empty($connections)) {
                $currentValue = $this->collectedValues[$keyName] ?? $this->existingEnv[$keyName] ?? null;
                $initial = $currentValue ?? $meta['default'];

                $defaultSelect = (string) $initial;
                if (! in_array($defaultSelect, $connections, true)) {
                    $defaultSelect = $connections[0];
                }

                if (! empty($defaultSelect)) {
                    $this->collectedValues[$keyName] = select(
                        label: "🔌 {$keyName}",
                        options: $connections,
                        default: $defaultSelect,
                        hint: $meta['description']
                    );

                    return true;
                }
            }
        }

        return false;
    }

    private function saveChanges(): int
    {
        if (empty($this->collectedValues)) {
            warning('⚠️  No changes to save.');

            return self::SUCCESS;
        }

        $filename = text(
            label: '📄 Enter the output filename:',
            default: $this->targetEnvFile,
            hint: 'The file will be saved in the project root.'
        );

        $targetPath = App::basePath($filename);

        if (file_exists($targetPath)) {
            if (! confirm("⚠️  File [{$filename}] already exists. Do you want to overwrite it?", default: false)) {
                warning('❌ Operation cancelled.');

                return self::SUCCESS;
            }
        }

        $writer = new EnvWriter($targetPath);
        $writer->update($this->collectedValues);
        info("✅ Successfully updated {$filename} file!");

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function getCurrentEnv(string $path): array
    {
        if (! file_exists($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return [];
        }

        $data = [];
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $data[trim($parts[0])] = trim($parts[1], "' ");
            }
        }

        return $data;
    }
}
