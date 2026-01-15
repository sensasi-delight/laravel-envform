<?php

declare(strict_types=1);

namespace EnvForm\Services;

use EnvForm\DTO\EnvVar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;

use function Laravel\Prompts\clear;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

final class Wizard
{
    final public function __construct(
        private readonly RuleEngine $ruleEngine,
        private readonly EnvironmentState $state
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
        $fileNames = $this->state->getFoundConfigFileNames();

        $menuOptions = [];
        foreach ($fileNames as $fileName) {
            $envKeys = $this->state->pending($fileName);

            $total = str_pad((string) $envKeys->count(), 2, ' ', STR_PAD_LEFT);

            $filled = str_pad(
                (string) $envKeys->filter(
                    function (EnvVar $item) {
                        $key = $item->key;
                        $val = $this->state->input($key)
                            ?? $this->state->getDotEnvValue($key);

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
            $this->getTriggerKeys($groupName) as $envVar
        ) {
            $this->askForValue($envVar);
        }

        foreach (
            $this->getNonTriggerKeys($groupName) as $envVar
        ) {
            $this->askForValue($envVar);
        }
    }

    /**
     * @return Collection<int, EnvVar>
     */
    private function getTriggerKeys(string $groupName): Collection
    {
        return $this->state->pending(
            $groupName,
        )->filter(
            fn (EnvVar $endDef) => $endDef->isTrigger
        );
    }

    /**
     * @return Collection<int, EnvVar>
     */
    private function getNonTriggerKeys(string $groupName): Collection
    {
        return $this->state->pending(
            $groupName,
        )->filter(
            fn (EnvVar $envVar) => $envVar
                ->group === $groupName && ! $envVar->isTrigger
        );
    }

    private function askForValue(EnvVar $envVar): void
    {
        if (! $this->ruleEngine->shouldAsk(
            $envVar
        )) {
            return;
        }

        if (
            $this->handleAppKey($envVar->key) ||
            $this->handleStrictKeys($envVar)
        ) {
            return;
        }

        $currentValue = $this->state->input($envVar->key)
            ?? Config::get($envVar->configKey)
            ?? $this->state->getDotEnvValue($envVar->key);

        $defaultValue = $envVar->default;

        $label = "👉 {$envVar->key}";
        $hint = $envVar->description;

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

            $this->state->setFormValue(
                $envVar->key,
                confirm(
                    label: $label,
                    default: (bool) $boolInitial,
                    hint: $hint
                )
            );

            return;
        }

        $this->state->setFormValue(
            $envVar->key,
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

        $currentValue = $this->state->input($keyName)
            ?? $this->state->getDotEnvValue($keyName);

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

        $this->state->setFormValue(
            $keyName,
            trim(Artisan::output())
        );

        return true;
    }

    private function handleStrictKeys(EnvVar $ekd): bool
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

        $this->state->setFormValue(
            $ekd->key,
            $this->buildSelect(
                label: "🔌 {$ekd->key}",
                optionsRefConfigKey: $ref,
                envVar: $ekd,
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
        EnvVar $envVar,
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

        $defaultValue = $this->state->input($envVar->key)
            ?? $envVar->currentValue
            ?? $envVar->default
            ?? $additionalDefaultOption;

        return select(
            label: $label,
            options: $availableOptions,
            default: $defaultValue,
            hint: $envVar->description,
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
                    (string) $this->state->pending()->count(),
                ], [
                    'ENV keys found in .env file',
                    (string) $this->state->getCountDotEnvKeyValuePairs(),
                ],
            ]
        );
    }
}
