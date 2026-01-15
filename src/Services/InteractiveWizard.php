<?php

declare(strict_types=1);

namespace EnvForm\Services;

use EnvForm\DTO\EnvKeyDefinition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;

use function Laravel\Prompts\clear;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

final class InteractiveWizard
{
    final public function __construct(
        private readonly DependencyResolver $dependencyResolver,
        private readonly KeyManager $keyManager
    ) {}

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
        $fileNames = $this->keyManager->getFoundConfigFileNames();

        $menuOptions = [];
        foreach ($fileNames as $fileName) {
            $envKeys = $this->keyManager->getShouldAskEnvKeys($fileName);

            $total = str_pad((string) $envKeys->count(), 2, ' ', STR_PAD_LEFT);

            $filled = str_pad(
                (string) $envKeys->filter(
                    function (EnvKeyDefinition $item) {
                        $key = $item->key;
                        $val = $this->keyManager->getFormValue($key)
                            ?? $this->keyManager->getDotEnvValue($key);

                        return ! empty($val) || $val === '0' || $val === false;
                    }
                )->count(),
                2,
                ' ',
                STR_PAD_LEFT
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
        return $this->keyManager->getShouldAskEnvKeys(
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
        return $this->keyManager->getShouldAskEnvKeys(
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
            $this->handleStrictKeys($envDef)
        ) {
            return;
        }

        $currentValue = $this->keyManager->getFormValue($envDef->key)
            ?? Config::get($envDef->configKey)
            ?? $this->keyManager->getDotEnvValue($envDef->key);

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

            $this->keyManager->setFormValue(
                $envDef->key,
                confirm(
                    label: $label,
                    default: (bool) $boolInitial,
                    hint: $hint
                )
            );

            return;
        }

        $this->keyManager->setFormValue(
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

        $currentValue = $this->keyManager->getFormValue($keyName)
            ?? $this->keyManager->getDotEnvValue($keyName);

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

        $this->keyManager->setFormValue(
            $keyName,
            trim(Artisan::output())
        );

        return true;
    }

    private function handleStrictKeys(EnvKeyDefinition $ekd): bool
    {
        $map = [
            'broadcast.default' => 'broadcasting.connections',
            'cache.default' => 'cache.stores',
            'queue.default' => 'queue.stores',
            'filesystem.default' => 'filesystem.disks',
            'database.default' => 'database.connections',
        ];

        $ref = $map[$ekd->configKey] ?? null;

        if (! $ref && preg_match('/^DB_(.*)_CONNECTION$/', $ekd->key)) {
            $ref = 'database.connections';
        }

        if (! $ref) {
            return false;
        }

        $this->keyManager->setFormValue(
            $ekd->key,
            $this->buildSelect(
                label: "🔌 {$ekd->key}",
                optionsRefConfigKey: $ref,
                envKeyDefinition: $ekd,
                additionalOptions: $ekd->configKey === 'cache.default' ? ['null'] : [],
                additionalDefaultOption: $ekd->configKey === 'database.default' ? Config::get('database.default') : null
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

        $defaultValue = $this->keyManager->getFormValue($envKeyDefinition->key)
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
                    (string) $this->keyManager->getShouldAskEnvKeys()->count(),
                ], [
                    'ENV keys found in .env file',
                    (string) $this->keyManager->getCountDotEnvKeyValuePairs(),
                ],
            ]
        );
    }
}
