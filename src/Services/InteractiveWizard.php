<?php

declare(strict_types=1);

namespace EnvForm\Services;

use EnvForm\DTO\EnvKeyDefinition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;

use function EnvForm\addLeadingWhitespace;
use function Laravel\Prompts\clear;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * @phpstan-type EnvValue bool|int|string|null
 * @phpstan-type CollectedValues array<string, EnvValue>
 */
final class InteractiveWizard
{
    /** @var CollectedValues */
    private array $collectedValues = [];

    /**
     * @param  Collection<int, EnvKeyDefinition>  $allKeys
     * @param  array<string, string>  $existingEnv
     */
    public function __construct(
        private readonly Collection $allKeys,
        private readonly array $existingEnv,
        private readonly DependencyResolver $dependencyResolver
    ) {}

    /**
     * @return CollectedValues
     */
    public function run(): array
    {
        $groupedKeys = $this->allKeys->groupBy('group')->sortKeys();
        $menuOptions = $this->buildMenuOptions($groupedKeys);

        while (true) {
            clear();
            $selectedGroup = select(
                label: '📂 Select a configuration file to configure',
                options: $menuOptions,
                default: 'EXIT',
                scroll: \count($menuOptions)
            );

            if ($selectedGroup === 'EXIT') {
                break;
            }

            $keys = $groupedKeys[$selectedGroup];
            $this->configureGroup((string) $selectedGroup, $keys);
        }

        return $this->collectedValues;
    }

    /**
     * @param  Collection<string, Collection<int, EnvKeyDefinition>>  $groupedKeys
     * @return array<string, string>
     */
    private function buildMenuOptions(Collection $groupedKeys): array
    {
        $menuOptions = [];
        foreach ($groupedKeys as $groupName => $keys) {
            $total = addLeadingWhitespace($keys->count());

            $filled = addLeadingWhitespace(
                $keys->filter(
                    function (EnvKeyDefinition $item) {
                        $key = $item->key;
                        $val = $this->collectedValues[$key] ?? $this->existingEnv[$key] ?? null;

                        return ! empty($val) || $val === '0' || $val === false;
                    }
                )->count()
            );

            $status = ($filled >= $total) ? '✅' : "({$filled}/{$total})";
            $menuOptions[$groupName] = "{$status} {$groupName}";
        }

        $menuOptions['EXIT'] = '💾 Save & Exit';

        return $menuOptions;
    }

    /**
     * @param  Collection<int, EnvKeyDefinition>  $keys
     */
    private function configureGroup(string $groupName, Collection $keys): void
    {
        info("🛠️  Configuring settings for: {$groupName}");

        // Sort keys: Triggers first, then others.
        $sortedKeys = $keys->sortBy(function (EnvKeyDefinition $meta) {
            $isTrigger = $this->dependencyResolver->isTrigger($meta->key, $this->allKeys);

            return $isTrigger ? 0 : 1;
        });

        foreach ($sortedKeys as $meta) {
            $this->askForValue($meta);
        }
    }

    private function askForValue(EnvKeyDefinition $meta): void
    {
        $keyName = $meta->key;

        // Check dependencies
        if (! $this->dependencyResolver->shouldAsk(
            envKey: $keyName,
            parsedConfig: $this->allKeys,
            currentValues: $this->collectedValues
        )) {
            return;
        }

        // Skip if special handling took care of it
        if ($this->handleSpecialKeys($keyName, $meta)) {
            return;
        }

        $currentValue = $this->collectedValues[$keyName] ?? Config::get($meta->configPath) ?? $this->existingEnv[$keyName] ?? null;
        $defaultValue = $meta->default;

        $label = "👉 {$keyName}";
        $hint = $meta->description;

        if ($defaultValue !== null) {
            $displayDefault = \is_bool($defaultValue) ? ($defaultValue ? 'true' : 'false') : (string) $defaultValue;
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

    private function handleSpecialKeys(string $keyName, EnvKeyDefinition $meta): bool
    {
        if ($keyName === 'APP_KEY') {
            $currentValue = $this->collectedValues[$keyName] ?? $this->existingEnv[$keyName] ?? null;
            if (confirm(
                label: '🔑 Do you want to generate/regenerate APP_KEY?',
                default: empty($currentValue)
            )) {
                Artisan::call(
                    command: 'key:generate',
                    parameters: ['--show' => true]
                );

                $this->collectedValues[$keyName] = trim(
                    string: Artisan::output()
                );

                return true;
            }

            return false;
        }

        $configMap = collect([
            [
                'config_path' => 'database.default',
                'env_key_pattern' => '/^DB_(.*)_CONNECTION$/',
                'config_key_options_ref' => 'database.connections',
            ],
            // 'queue.default' => 'queue.stores',
            // 'broadcast.default' => 'broadcasting.connections',
            [
                'config_path' => 'cache.default',
                'env_key_pattern' => null,
                'config_key_options_ref' => 'cache.stores',
            ],
            // 'cache.default' => 'cache.stores',
            // 'filesystem.default' => 'filesystem.disks',
        ]);

        $keyConfigPath = $meta->configPath;

        if (
            $foundConfigKey = $configMap->first(
                callback: fn (
                    array $item
                ) => $keyConfigPath === $item['config_path'] || (
                    $item['env_key_pattern'] !== null &&
                    preg_match(
                        pattern: $item['env_key_pattern'],
                        subject: $keyName
                    ))
            )
        ) {
            /** @var array<int, string> $options */
            $options = array_map('strval', array_keys(
                array: Config::get(
                    key: $foundConfigKey['config_key_options_ref'],
                    default: []
                )
            ));

            if (! empty($options)) {
                $currentValue = $this->collectedValues[$keyName] ?? Config::get($keyConfigPath) ?? $this->existingEnv[$keyName] ?? null;
                $initial = $currentValue ?? $meta->default;

                $defaultSelect = (string) $initial;
                if (! \in_array(
                    needle: $defaultSelect,
                    haystack: $options,
                    strict: true
                )) {
                    $defaultSelect = $options[0];
                }

                if (! empty($defaultSelect)) {
                    $this->collectedValues[$keyName] = select(
                        label: "🔌 {$keyName}",
                        options: $options,
                        default: $defaultSelect,
                        hint: $meta->description,
                        scroll: \count($options)
                    );

                    return true;
                }
            }
        }

        return false;
    }
}
