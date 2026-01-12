<?php

declare(strict_types=1);

namespace EnvForm\Console\Commands;

use EnvForm\Services\ConfigScanner;
use EnvForm\Services\EnvWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;

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
    protected $description = 'Interactively manage .env file based on config scanning.';

    final public function handle(ConfigScanner $scanner): int
    {
        info('Welcome to Laravel EnvForm! 🚀');
        note('We will scan your config files and help you set up your environment variables.');

        /** @var string $configPath */
        $configPath = App::configPath();

        /** @var string $envPath */
        $envPath = App::basePath('.env');

        // 1. Scan
        info("Scanning {$configPath}...");
        $allKeys = $scanner->scan($configPath);

        if ($allKeys->isEmpty()) {
            warning('No env() calls found in config/*.php');

            return self::FAILURE;
        }

        note("Found {$allKeys->count()} potential environment variables.");

        $collectedValues = [];
        $existingEnv = $this->getCurrentEnv($envPath);
        $groupedKeys = $allKeys->groupBy('group')->sortKeys();

        while (true) {
            // Calculate progress for menu
            $menuOptions = [];
            foreach ($groupedKeys as $groupName => $keys) {
                /** @var string $groupName */
                $total = $keys->count();
                $filled = $keys->filter(function ($item) use ($collectedValues, $existingEnv) {
                    $key = $item['key'];
                    $val = $collectedValues[$key] ?? $existingEnv[$key] ?? null;

                    return ! empty($val) || $val === '0' || $val === false;
                })->count();

                $status = ($filled >= $total) ? '✅' : "({$filled}/{$total})";
                $menuOptions[$groupName] = "{$groupName} {$status}";
            }

            $menuOptions['EXIT'] = '💾 Save & Exit';

            $selectedGroup = select(
                label: 'Select a configuration file to configure:',
                options: $menuOptions,
                default: 'EXIT',
                scroll: 15
            );

            if ($selectedGroup === 'EXIT') {
                break;
            }

            // Process selected group
            $currentGroupKeys = $groupedKeys[$selectedGroup];
            info("Configuring {$selectedGroup}...");

            foreach ($currentGroupKeys as $meta) {
                $keyName = (string) $meta['key'];
                /** @var array{group: string, description: string, default: mixed} $meta */

                // Use collected value if exists (edit mode), otherwise existing env, otherwise default
                $currentValue = $collectedValues[$keyName] ?? $existingEnv[$keyName] ?? null;
                $defaultValue = $meta['default'];

                $label = "{$keyName}";
                $hint = $meta['description'];

                if ($defaultValue !== null) {
                    $displayDefault = is_bool($defaultValue) ? ($defaultValue ? 'true' : 'false') : (string) $defaultValue;
                    $hint .= " (Default: {$displayDefault})";
                }

                $initial = $currentValue ?? $defaultValue;

                // Special handling for APP_KEY
                if ($keyName === 'APP_KEY') {
                    if (confirm('Do you want to generate/regenerate APP_KEY?', default: empty($currentValue))) {
                        Artisan::call('key:generate', ['--show' => true]);
                        $collectedValues[$keyName] = trim(Artisan::output());

                        continue;
                    }
                }

                // Special handling for Connections
                $connectionConfigMap = [
                    'DB_CONNECTION' => 'database.connections',
                    'QUEUE_CONNECTION' => 'queue.connections',
                    'BROADCAST_CONNECTION' => 'broadcasting.connections',
                ];

                if (\array_key_exists($keyName, $connectionConfigMap)) {
                    /** @var array<int, string> $connections */
                    $connections = array_keys(Config::get($connectionConfigMap[$keyName], []));

                    if (! empty($connections)) {
                        $defaultSelect = (string) $initial;

                        if (! \in_array($defaultSelect, $connections, true)) {
                            $defaultSelect = $connections[0];
                        }

                        if (! empty($defaultSelect)) {
                            $collectedValues[$keyName] = select(
                                label: $label,
                                options: $connections,
                                default: $defaultSelect,
                                hint: $hint
                            );

                            continue;
                        }
                    }
                }

                // Special handling for Boolean
                if (is_bool($defaultValue)) {
                    $boolInitial = $initial;
                    if (is_string($initial)) {
                        $boolInitial = strtolower($initial) === 'true';
                    }

                    $collectedValues[$keyName] = confirm(
                        label: $label,
                        default: (bool) $boolInitial,
                        hint: $hint
                    );

                    continue;
                }

                // Standard input
                $collectedValues[$keyName] = text(
                    label: $label,
                    default: (string) $initial,
                    hint: $hint
                );
            }
        }

        // 4. Save
        if (empty($collectedValues)) {
            warning('No changes to save.');

            return self::SUCCESS;
        }

        $filename = text(
            label: 'Enter the output filename:',
            default: '.env',
            hint: 'The file will be saved in the project root.'
        );

        $targetPath = App::basePath($filename);

        if (file_exists($targetPath)) {
            if (! confirm("File [{$filename}] already exists. Do you want to overwrite it?", default: false)) {
                warning('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        $writer = new EnvWriter($targetPath);
        $writer->update($collectedValues);
        info("Successfully updated {$filename} file!");

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
            if (\count($parts) === 2) {
                $data[trim($parts[0])] = trim($parts[1], "' ");
            }
        }

        return $data;
    }
}
