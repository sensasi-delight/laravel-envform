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

final class InteractiveWizard
{
    private readonly DependencyResolver $dependencyResolver;

    final public function __construct()
    {
        $this->dependencyResolver = new DependencyResolver(new KeyManagerFormValueProvider);
    }

    final public function run(): void
    {
        while (true) {
            clear();
            $this->showSummaryTable();

            $menuOptions = $this->buildMenuOptions();

            $selectedGroup = select(
                label: '📂 Select a configuration file to configure',
                options: $menuOptions,
                default: 'EXIT',
                scroll: \count($menuOptions)
            );

            if ($selectedGroup === 'EXIT') {
                break;
            }

            $this->configureGroup((string) $selectedGroup);
        }
    }

    /**
     * @return array<string, string>
     */
    private function buildMenuOptions(): array
    {
        $fileNames = KeyManager::getFoundConfigFileNames();

        $menuOptions = [];
        foreach ($fileNames as $fileName) {
            $envKeys = KeyManager::getShouldAskEnvKeys($fileName);

            $total = addLeadingWhitespace($envKeys->count());

            $filled = addLeadingWhitespace(
                $envKeys->filter(
                    function (EnvKeyDefinition $item) {
                        $key = $item->key;
                        $val = KeyManager::getFormValue($key)
                            ?? KeyManager::getDotEnvValue($key);

                        return ! empty($val) || $val === '0' || $val === false;
                    }
                )->count()
            );

            $status = ($filled >= $total) ? '✅' : "({$filled}/{$total})";
            $menuOptions[$fileName] = "{$status} {$fileName}";
        }

        $menuOptions['EXIT'] = '💾 Save & Exit';

        return $menuOptions;
    }

    private function configureGroup(string $groupName): void
    {
        info("🛠️  Configuring settings for: {$groupName}");

        foreach (
            $this->getTriggerKeys($groupName) as $envKey
        ) {
            $this->askForValue($envKey);
        }

        foreach (
            $this->getNonTriggerKeys($groupName) as $envKey
        ) {
            $this->askForValue($envKey);
        }
    }

    /**
     * @return Collection<int, EnvKeyDefinition>
     */
    private function getTriggerKeys(string $groupName): Collection
    {
        return KeyManager::getShouldAskEnvKeys(
            $groupName,
        )->filter(
            fn (EnvKeyDefinition $endDef) => $this->dependencyResolver->isTrigger(
                $endDef,
            )
        );
    }

    /**
     * @return Collection<int, EnvKeyDefinition>
     */
    private function getNonTriggerKeys(string $groupName): Collection
    {
        return KeyManager::getShouldAskEnvKeys(
            $groupName,
        )->filter(
            fn (EnvKeyDefinition $envDef) => $envDef
                ->group === $groupName && ! $this->dependencyResolver->isTrigger(
                    $envDef,
                )
        );
    }

    private function askForValue(EnvKeyDefinition $envDef): void
    {
        if (! $this->dependencyResolver->shouldAsk(
            $envDef
        )) {
            return;
        }

        if (
            $this->handleAppKey($envDef->key) ||
            $this->handleDbConnection($envDef) ||
            $this->handleEnvKeyWithStrictOptions($envDef)
        ) {
            return;
        }

        $currentValue = KeyManager::getFormValue($envDef->key)
            ?? Config::get($envDef->configKey)
            ?? KeyManager::getDotEnvValue($envDef->key);

        $defaultValue = $envDef->default;

        $label = "👉 {$envDef->key}";
        $hint = $envDef->description;

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

            KeyManager::setFormValue(
                $envDef->key,
                confirm(
                    label: $label,
                    default: (bool) $boolInitial,
                    hint: $hint
                )
            );

            return;
        }

        KeyManager::setFormValue(
            $envDef->key,
            text(
                label: $label,
                default: (string) $initial,
                hint: $hint
            )
        );
    }

    private function handleAppKey(string $keyName): bool
    {
        if ($keyName !== 'APP_KEY') {
            return false;
        }

        $currentValue = KeyManager::getFormValue($keyName)
            ?? KeyManager::getDotEnvValue($keyName);

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

        KeyManager::setFormValue(
            $keyName,
            trim(Artisan::output())
        );

        return true;
    }

    private function handleEnvKeyWithStrictOptions(
        EnvKeyDefinition $ekd
    ): bool {
        /**
         * [config key => config key option reference]
         *
         * @var array<string, string>
         */
        $configKeyOptionRefs = [
            'broadcast.default' => 'broadcasting.connections',
            'cache.default' => 'cache.stores',
            'queue.default' => 'queue.stores',
            'filesystem.default' => 'filesystem.disks',
        ];

        if (! isset($configKeyOptionRefs[$ekd->configKey])) {
            return false;
        }

        $configAdditionalOptions = [
            'cache.default' => ['null'],
        ];

        KeyManager::setFormValue(
            $ekd->key,
            $this->buildSelect(
                label: "🔌 {$ekd->key}",
                optionsRefConfigKey: $configKeyOptionRefs[$ekd->configKey],
                envKeyDefinition: $ekd,
                additionalOptions: $configAdditionalOptions[$ekd->configKey] ?? []
            )
        );

        return true;
    }

    private function handleDbConnection(
        EnvKeyDefinition $ekd
    ): bool {
        if (
            $ekd->configKey !== 'database.default' &&
            ! preg_match(
                pattern: '/^DB_(.*)_CONNECTION$/',
                subject: $ekd->key
            )
        ) {
            return false;
        }

        KeyManager::setFormValue(
            $ekd->key,
            $this->buildSelect(
                label: "🔌 {$ekd->key}",
                optionsRefConfigKey: 'database.connections',
                envKeyDefinition: $ekd,
                additionalDefaultOption: Config::get('database.default')
            )
        );

        return true;
    }

    /**
     * @param  string[]  $additionalOptions
     */
    private function buildSelect(
        EnvKeyDefinition $envKeyDefinition,
        string $optionsRefConfigKey,
        string $label,
        array $additionalOptions = [],
        ?string $additionalDefaultOption = null
    ): int|string {
        /** @var string[] */
        $availableOptions = [
            ...array_keys(
                array: Config::get(
                    key: $optionsRefConfigKey,
                    default: []
                )
            ),
            ...$additionalOptions,
        ];

        $defaultValue = KeyManager::getFormValue($envKeyDefinition->key)
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
                    'ENV keys need to be configured',
                    (string) KeyManager::getShouldAskEnvKeys()->count(),
                ], [
                    'ENV keys found in .env file',
                    (string) KeyManager::getCountDotEnvKeyValuePairs(),
                ],
            ]
        );
    }
}
