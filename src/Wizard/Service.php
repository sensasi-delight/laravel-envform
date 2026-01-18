<?php

declare(strict_types=1);

namespace EnvForm\Wizard;

use EnvForm\DotEnv;
use EnvForm\DTO\EnvVar;
use EnvForm\FormValue;
use EnvForm\Hint;
use EnvForm\Registry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;

use function Laravel\Prompts\clear;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

final readonly class Service
{
    final public function __construct(
        private DotEnv\Service $dotEnv,
        private FormValue\Service $formValue,
        private Hint\Service $hint,
        private Registry\Service $registry,
        private ShouldAsk $shouldAsk
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
                default: 'exit',
                scroll: \count($menuOptions)
            );

            if ($selectedGroup === 'exit') {
                break;
            }

            $this->configureGroup((string) $selectedGroup.'.php');
        }
    }

    /**
     * @return array<string, string>
     */
    private function buildMenuOptions(): array
    {
        $groups = $this->registry->groups();

        $menuOptions = [];
        foreach ($groups as $group) {
            $askVars = $this->registry
                ->all()
                ->filter(fn (EnvVar $v) => $v->group === $group)
                ->filter(fn (EnvVar $v) => $this->shouldAsk->shouldAsk($v));

            $envCount = str_pad(
                (string) $askVars->count(),
                2,
                ' ',
                STR_PAD_LEFT
            );

            $filled = str_pad(
                (string) $askVars->filter(
                    function (EnvVar $item) {
                        $key = $item->key;
                        $val = $this->formValue->get($key)
                            ?? $this->dotEnv->getExistingValue($key);

                        return ! empty($val) || $val === '0' || $val === false;
                    }
                )->count(),
                2,
                ' ',
                STR_PAD_LEFT
            );

            $status = ($filled >= $envCount) ? '✅' : "({$filled}/{$envCount})";

            $selectValue = str_replace(
                '.php',
                '',
                $group
            );
            $menuOptions[$selectValue] = "{$status} {$group}";
        }

        $menuOptions['exit'] = '💾 Save & Exit';

        return $menuOptions;
    }

    private function configureGroup(string $groupName): void
    {
        info("🛠️  Configuring settings for: {$groupName}");

        $triggerEnvVars = $this->registry->all()
            ->filter(fn (EnvVar $v) => $v->group === $groupName)
            ->filter(fn (EnvVar $v) => $v->isTrigger)
            ->filter(fn (EnvVar $v) => $this->shouldAsk->shouldAsk($v));

        foreach ($triggerEnvVars as $envVar) {
            $this->askForValue($envVar);
        }

        $nonTriggerEnvVars = $this->registry->all()
            ->filter(fn (EnvVar $v) => $v->group === $groupName)
            ->filter(fn (EnvVar $v) => ! $v->isTrigger)
            ->filter(fn (EnvVar $v) => $this->shouldAsk->shouldAsk($v));

        foreach ($nonTriggerEnvVars as $envVar) {
            $this->askForValue($envVar);
        }
    }

    private function askForValue(EnvVar $envVar): void
    {
        if (
            $this->handleAppKey($envVar->key) ||
            $this->handleStrictKeys($envVar)
        ) {
            return;
        }

        $currentValue = $this->formValue->get($envVar->key)
            ?? Config::get($envVar->configKeys[0])
            ?? $this->dotEnv->getExistingValue($envVar->key);

        $defaultValue = $envVar->default;

        $label = "👉 {$envVar->key}";
        $hint = $this->hint->get($envVar->configKeys[0]);

        if ($defaultValue !== null) {
            $displayDefault = \is_bool($defaultValue) ? ($defaultValue ? 'true' : 'false') : (string) $defaultValue;
            $hint .= " (Default: {$displayDefault})";
        }

        $initial = $currentValue ?? $defaultValue;

        if (\is_bool($defaultValue)) {
            $boolInitial = $initial;
            if (\is_string($initial)) {
                $boolInitial = strtolower($initial) === 'true';
            }

            $this->formValue->set(
                $envVar->key,
                confirm(
                    label: $label,
                    default: (bool) $boolInitial,
                    hint: $hint
                )
            );

            return;
        }

        $this->formValue->set(
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

        $currentValue = $this->formValue->get($keyName)
            ?? $this->dotEnv->getExistingValue($keyName);

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

        $this->formValue->set(
            $keyName,
            trim(Artisan::output())
        );

        return true;
    }

    private function handleStrictKeys(EnvVar $ekd): bool
    {
        $map = [
            'cache.default' => 'cache.stores',
            'database.default' => 'database.connections',
            'filesystem.default' => 'filesystem.disks',
            'logging.default' => 'logging.channels',
            'mail.default' => 'mail.mailers',
            'queue.default' => 'queue.stores',
            'cache.stores.redis.connection' => 'database.redis',
            'cache.stores.redis.lock_connection' => 'database.redis',
        ];

        $ref = null;

        foreach ($ekd->configKeys as $configKey) {
            if (! empty($map[$configKey])) {
                $ref = $map[$configKey];
                break;
            }
        }

        if (! $ref && preg_match('/^DB_(.*)_CONNECTION$/', $ekd->key)) {
            $ref = 'database.connections';
        }

        if (! $ref) {
            return false;
        }

        $this->formValue->set(
            $ekd->key,
            $this->buildSelect(
                label: "🔌 {$ekd->key}",
                optionsRefConfigKey: $ref,
                envVar: $ekd,
                additionalOptions: $ekd->configKeys->contains('cache.default') ? ['null'] : [],
                additionalDefaultOption: $ekd->configKeys->contains('database.default') ? Config::get('database.default') : null
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

        /** @var string[] $availableOptions */
        $availableOptions = [
            ...array_keys(Config::get(
                $optionsRefConfigKey
            )),
            ...$additionalOptions,
        ];

        $defaultValue = $this->formValue->get($envVar->key)
            ?? $this->dotEnv->getExistingValue($envVar->key)
            ?? $envVar->default
            ?? $additionalDefaultOption;

        return select(
            label: $label,
            options: $availableOptions,
            default: (string) $defaultValue,
            hint: $this->hint->get($envVar->configKeys[0]),
            scroll: \count($availableOptions)
        );
    }

    private function showSummaryTable(): void
    {
        table(
            ['Summary', ''],
            [
                [
                    'ENV vars need configuration',
                    (string) $this->getPendingCount(),
                ], [
                    'Existing vars in file',
                    (string) $this->dotEnv->getCount(),
                ],
            ]
        );
    }

    private function getPendingCount(): int
    {
        return $this->registry->all()
            ->filter(fn (EnvVar $var) => $this->shouldAsk->shouldAsk($var))
            ->count();
    }
}
