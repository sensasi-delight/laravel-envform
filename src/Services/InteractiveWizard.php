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
use function Laravel\Prompts\table;
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
        while (true) {
            clear();
            $this->showSummaryTable();

            $groupedKeys = KeyManager::getShouldAskEnvKeys()->groupBy('group')->sortKeys();
            $menuOptions = $this->buildMenuOptions($groupedKeys);
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

        if (
            $this->handleAppKey($keyName) ||
            $this->handleDbConnection($meta) ||
            $this->handleEnvKeyWithStrictOptions($meta)
        ) {
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

        if (\is_bool($defaultValue)) {
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

    private function handleAppKey(string $keyName): bool
    {
        if ($keyName !== 'APP_KEY') {
            return false;
        }

        $currentValue = $this->collectedValues[$keyName] ?? $this->existingEnv[$keyName] ?? null;

        if (! confirm(
            label: '🔑 Do you want to generate/regenerate APP_KEY?',
            default: empty($currentValue)
        )) {
            return false;
        }

        Artisan::call(
            command: 'key:generate',
            parameters: ['--show' => true]
        );

        $this->collectedValues[$keyName] = trim(
            string: Artisan::output()
        );

        return true;
    }

    private function handleEnvKeyWithStrictOptions(
        EnvKeyDefinition $ekd
    ): bool {
        /**
         * [config path => config path option ref]
         *
         * @var array<string, string>
         */
        $configPathOptionRefs = [
            'broadcast.default' => 'broadcasting.connections',
            'cache.default' => 'cache.stores',
            'queue.default' => 'queue.stores',
            'filesystem.default' => 'filesystem.disks',
        ];

        if (! isset($configPathOptionRefs[$ekd->configPath])) {
            return false;
        }

        $configAdditionalOptions = [
            'cache.default' => ['null'],
        ];

        $this->collectedValues[$ekd->key] = $this->buildSelect(
            label: "🔌 {$ekd->key}",
            configPathForOptions: $configPathOptionRefs[$ekd->configPath],
            envKeyDefinition: $ekd,
            additionalOptions: $configAdditionalOptions[$ekd->configPath] ?? []
        );

        return true;
    }

    private function handleDbConnection(
        EnvKeyDefinition $ekd
    ): bool {
        if (
            $ekd->configPath !== 'database.default' &&
            ! preg_match(
                pattern: '/^DB_(.*)_CONNECTION$/',
                subject: $ekd->key
            )
        ) {
            return false;
        }

        $this->collectedValues[$ekd->key] = $this->buildSelect(
            label: "🔌 {$ekd->key}",
            configPathForOptions: 'database.connections',
            envKeyDefinition: $ekd,
            additionalDefaultOption: Config::get('database.default')
        );

        return true;
    }

    /**
     * @param  string[]  $additionalOptions
     */
    private function buildSelect(
        EnvKeyDefinition $envKeyDefinition,
        string $configPathForOptions,
        string $label,
        array $additionalOptions = [],
        ?string $additionalDefaultOption = null
    ): int|string {
        /** @var string[] */
        $availableOptions = [
            ...array_keys(
                array: Config::get(
                    key: $configPathForOptions,
                    default: []
                )
            ),
            ...$additionalOptions,
        ];

        $defaultValue = $this->collectedValues[$envKeyDefinition->key]
            ?? $envKeyDefinition->currentValue
            ?? $envKeyDefinition->default
            ?? $additionalDefaultOption;

        return select(
            label: $label,
            options: $availableOptions,
            default: $defaultValue,
            hint: $envKeyDefinition->description,
            scroll: \count($availableOptions)
        );
    }

    private function showSummaryTable(): void
    {
        table(
            [
                'Summary',
                '',
            ],
            [
                [
                    'ENV keys need to be configured', KeyManager::getShouldAskEnvKeys()->count(),
                ], [
                    'ENV keys found in .env file', KeyManager::getDotEnvKeyValuePairs()->count(),
                ],
            ]
        );
    }
}
