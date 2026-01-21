<?php

declare(strict_types=1);

namespace EnvForm\Wizard;

use EnvForm\DotEnv;
use EnvForm\DTO\EnvVar;
use EnvForm\FormValue;
use EnvForm\Hint;
use EnvForm\KeyGenerator;
use EnvForm\OptionResolver;
use EnvForm\Registry;
use EnvForm\ShouldAsk;

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
        private ShouldAsk\Service $shouldAsk,
        private KeyGenerator\Service $keyGenerator,
        private OptionResolver\Service $optionResolver,
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
            $askVars = $this->shouldAsk->getVisibleVariablesByGroup($group);

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

        $triggerEnvVars = $this->shouldAsk->getVisibleVariablesByGroup($groupName)
            ->filter(fn (EnvVar $v) => $v->isTrigger);

        foreach ($triggerEnvVars as $envVar) {
            $this->askForValue($envVar);
        }

        $this->shouldAsk->refresh();

        $nonTriggerEnvVars = $this->shouldAsk
            ->getVisibleVariablesByGroup($groupName)
            ->filter(fn (EnvVar $v) => ! $v->isTrigger);

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
            ?? $this->registry->getStaticValue(
                $envVar->configKeys->first()
            )
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

        $this->formValue->set(
            $keyName,
            $this->keyGenerator->generate()
        );

        return true;
    }

    private function handleStrictKeys(EnvVar $ekd): bool
    {
        $options = $this->optionResolver->resolveOptions($ekd);

        if ($options === null) {
            return false;
        }

        $additionalOptions = $ekd->configKeys->contains('cache.default') ? ['null'] : [];
        foreach ($additionalOptions as $opt) {
            $options[$opt] = $opt;
        }

        $additionalDefaultOption = null;
        if ($ekd->configKeys->contains('database.default')) {
            $additionalDefaultOption = $this->registry->getStaticValue('database.default');
        }

        $this->formValue->set(
            $ekd->key,
            $this->buildSelect(
                label: "🔌 {$ekd->key}",
                options: $options,
                envVar: $ekd,
                additionalDefaultOption: (string) $additionalDefaultOption
            )
        );

        return true;
    }

    /**
     * @param  array<string, string>  $options
     */
    private function buildSelect(
        EnvVar $envVar,
        array $options,
        string $label,
        ?string $additionalDefaultOption = null
    ): int|string {
        $defaultValue = $this->formValue->get($envVar->key)
            ?? $this->dotEnv->getExistingValue($envVar->key)
            ?? $envVar->default
            ?? $additionalDefaultOption;

        return select(
            label: $label,
            options: $options,
            default: (string) $defaultValue,
            hint: $this->hint->get($envVar->configKeys[0]),
            scroll: \count($options)
        );
    }

    private function showSummaryTable(): void
    {
        table(
            ['Summary', ''],
            [
                [
                    'ENV vars need configuration',
                    (string) $this->shouldAsk->countVisible(),
                ], [
                    'Existing vars in file',
                    (string) $this->dotEnv->getCount(),
                ],
            ]
        );
    }
}
