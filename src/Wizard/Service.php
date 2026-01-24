<?php

declare(strict_types=1);

namespace EnvForm\Wizard;

use EnvForm\Console\Components\Header;
use EnvForm\DotEnv;
use EnvForm\DTO\EnvVar;
use EnvForm\DTO\NavigationSession;
use EnvForm\FormValue;
use EnvForm\Hint;
use EnvForm\KeyGenerator;
use EnvForm\OptionResolver;
use EnvForm\Registry;
use EnvForm\ShouldAsk;
use Laravel\Prompts\ConfirmPrompt;
use Laravel\Prompts\Exceptions\FormRevertedException;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\SelectPrompt;
use Laravel\Prompts\TextPrompt;

use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;

final class Service
{
    final public function __construct(
        private readonly DotEnv\Service $dotEnv,
        private readonly FormValue\Service $formValue,
        private readonly Hint\Service $hint,
        private readonly Registry\Service $registry,
        private readonly ShouldAsk\Service $shouldAsk,
        private readonly KeyGenerator\Service $keyGenerator,
        private readonly OptionResolver\Service $optionResolver,
        private readonly \EnvForm\ValueResolver\Service $valueResolver,
    ) {}

    final public function run(): void
    {
        while (true) {
            Header::render();
            $this->shouldAsk->refresh($this->dotEnv->getExistingValues());
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
        $vars = $this->registry->all()
            ->filter(fn (EnvVar $v) => $v->group === $groupName)
            ->values();

        if ($vars->isEmpty()) {
            info("✅ No variables found for: {$groupName}");

            return;
        }

        $session = new NavigationSession($vars);

        $this->runFormLoop($session);

        $this->shouldAsk->refresh($this->dotEnv->getExistingValues());

        info("✨ Configuration for {$groupName} completed.");
    }

    private function runFormLoop(NavigationSession $session): void
    {
        $form = \Laravel\Prompts\form();

        foreach ($session->steps as $index => $envVar) {
            $form->addIf(
                function (array $responses) use ($envVar, $session, $index): bool {
                    // Sync previous responses to FormValue so ShouldAsk has latest state
                    foreach ($session->steps as $sIndex => $sVar) {
                        if ($sIndex >= $index) {
                            break;
                        }
                        if (isset($responses[$sVar->key])) {
                            $this->formValue->set($sVar->key, $responses[$sVar->key]);
                        }
                    }

                    $this->shouldAsk->refresh($this->dotEnv->getExistingValues());

                    if (! $this->shouldAsk->isVisible($envVar)) {
                        return false;
                    }

                    // Stable UI: Clear and re-render everything before showing the next prompt.
                    Header::render();
                    $this->showSummaryTable();

                    foreach ($session->steps as $sIndex => $sVar) {
                        if ($sIndex >= $index) {
                            break;
                        }

                        $key = $sVar->key;
                        if (isset($responses[$key]) && $this->shouldAsk->isVisible($sVar)) {
                            $val = $responses[$key];
                            $prefix = $sVar->isTrigger ? '🚀' : '⚙️';
                            $progress = $this->getVisibleProgressLabel($sVar);
                            info("{$prefix} {$progress} {$key}: ".(\is_bool($val) ? ($val ? 'true' : 'false') : (string) $val));
                        }
                    }

                    return true;
                },
                function () use ($envVar, $session, $index) {
                    $session->currentIndex = $index;

                    return $this->renderStep($envVar, $session);
                },
                name: $envVar->key
            );
        }

        try {
            $form->submit();
        } catch (\EnvForm\Exceptions\BackToMenuException) {
            // Exit loop and return to menu selection
        }
    }

    private function renderStep(EnvVar $envVar, NavigationSession $session): mixed
    {
        try {
            $result = $this->askForValue($envVar, $session);

            if ($result === 'null') {
                $result = null;
            }

            // Sync current step result immediately to FormValue
            if ($result !== null || $this->formValue->has($envVar->key)) {
                $this->formValue->set($envVar->key, $result);

                if ($envVar->isTrigger) {
                    $oldValue = $this->dotEnv->getExistingValue($envVar->key);

                    if ($oldValue != $result) {
                        $this->shouldAsk->refresh($this->dotEnv->getExistingValues());
                        info("🔄 Visibility updated based on your choice for {$envVar->key}");
                    }
                }
            }

            return $result;
        } catch (FormRevertedException $e) {
            throw $e;
        }
    }

    private function askForValue(EnvVar $envVar, NavigationSession $session): mixed
    {
        if ($keyVal = $this->handleAppKey($envVar, $session)) {
            return $keyVal;
        }

        if ($strictVal = $this->handleStrictKeys($envVar, $session)) {
            return $strictVal;
        }

        $initial = $this->valueResolver->resolve($envVar->key);
        $defaultValue = $envVar->default;

        $prefix = $envVar->isTrigger ? '🚀' : '⚙️';
        $progress = $this->getVisibleProgressLabel($envVar);

        $navigationLabel = '';
        if ($session->hasPrevious() && PHP_OS_FAMILY !== 'Windows') {
            $navigationLabel = " \e[2m(Ctrl+C: Back)\e[22m";
        }

        $label = "{$prefix} {$progress} {$envVar->key}{$navigationLabel}";
        $hint = $this->hint->get($envVar->configKeys[0]);

        if ($defaultValue !== null) {
            $displayDefault = \is_bool($defaultValue) ? ($defaultValue ? 'true' : 'false') : (string) $defaultValue;
            $hint .= " (Default: {$displayDefault})";
        }

        if (\is_bool($defaultValue)) {
            $boolInitial = $initial;
            if (\is_string($initial)) {
                $boolInitial = strtolower($initial) === 'true';
            }

            $res = $this->runPromptWithBackSupport(
                new ConfirmPrompt(
                    label: $label,
                    default: (bool) $boolInitial,
                    hint: $hint
                ),
                $session,
                $envVar
            );

            $this->formValue->set($envVar->key, $res);
            $this->shouldAsk->refresh($this->dotEnv->getExistingValues());

            return $res;
        }

        $res = $this->runPromptWithBackSupport(
            new TextPrompt(
                label: $label,
                default: (string) $initial,
                hint: $hint
            ),
            $session,
            $envVar
        );

        $this->formValue->set($envVar->key, $res);
        $this->shouldAsk->refresh($this->dotEnv->getExistingValues());

        return $res;
    }

    private function handleAppKey(EnvVar $envVar, NavigationSession $session): mixed
    {
        if ($envVar->key !== 'APP_KEY') {
            return false;
        }

        $currentValue = $this->valueResolver->resolve($envVar->key);

        $prefix = '🚀';
        $progress = $this->getVisibleProgressLabel($envVar);

        $navigationLabel = '';
        if ($session->hasPrevious() && PHP_OS_FAMILY !== 'Windows') {
            $navigationLabel = " \e[2m(Ctrl+C: Back)\e[22m";
        }

        $answer = $this->runPromptWithBackSupport(
            new ConfirmPrompt(
                label: "{$prefix} {$progress} Do you want to generate/regenerate APP_KEY?{$navigationLabel}",
                default: empty($currentValue)
            ),
            $session,
            $envVar
        );

        if (! $answer) {
            return $currentValue;
        }

        return $this->keyGenerator->generate();
    }

    private function handleStrictKeys(EnvVar $ekd, NavigationSession $session): mixed
    {
        try {
            $options = $this->optionResolver->resolveOptions($ekd);
        } catch (\EnvForm\Exceptions\BackToMenuException $e) {
            \Laravel\Prompts\warning('⚠️ '.$e->getMessage());

            return false;
        }

        if ($options === null) {
            return false;
        }

        $additionalDefaultOption = null;
        if ($ekd->configKeys->contains('database.default')) {
            $additionalDefaultOption = $this->registry->getStaticValue('database.default');
        }

        $prefix = $ekd->isTrigger ? '🚀' : '⚙️';
        $progress = $this->getVisibleProgressLabel($ekd);

        $navigationLabel = '';
        if ($session->hasPrevious() && PHP_OS_FAMILY !== 'Windows') {
            $navigationLabel = " \e[2m(Ctrl+C: Back)\e[22m";
        }

        return $this->buildSelect(
            label: "{$prefix} {$progress} {$ekd->key}{$navigationLabel}",
            options: $options,
            envVar: $ekd,
            additionalDefaultOption: (string) $additionalDefaultOption,
            session: $session
        );
    }

    private function hasVisiblePrevious(EnvVar $currentVar): bool
    {
        $visibleVars = $this->shouldAsk->getVisibleVariablesByGroup($currentVar->group);

        if ($visibleVars->isEmpty()) {
            return false;
        }

        return $visibleVars->first()->key !== $currentVar->key;
    }

    private function getVisibleProgressLabel(EnvVar $currentVar): string
    {

        $visibleVars = $this->shouldAsk->getVisibleVariablesByGroup($currentVar->group)->values();

        $index = $visibleVars->search(fn ($v) => $v->key === $currentVar->key);

        if ($index === false) {

            return '';

        }

        $current = (int) $index + 1;

        $total = $visibleVars->count();

        return "[{$current}/{$total}]";

    }

    /**
     * @param  array<string, string|null>  $options
     */
    private function buildSelect(

        EnvVar $envVar,

        array $options,

        string $label,

        ?string $additionalDefaultOption = null,

        ?NavigationSession $session = null

    ): mixed {
        $defaultValue = $this->valueResolver->resolve($envVar->key)
            ?? $additionalDefaultOption;

        $hint = $this->hint->get($envVar->configKeys[0]);

        $options = array_map(fn ($v) => $v ?? 'null', $options);

        return $this->runPromptWithBackSupport(
            new SelectPrompt(
                label: $label,
                options: $options,
                default: (string) $defaultValue,
                hint: $hint,
                scroll: \count($options)
            ),
            $session,
            $envVar
        );
    }

    private function runPromptWithBackSupport(Prompt $prompt, ?NavigationSession $session, EnvVar $envVar): mixed
    {

        $prompt->on('key', function (string $key) use ($envVar) {

            if ($key === Key::CTRL_C) {

                // If there are no visible variables before this one in the group, return to menu

                if (! $this->hasVisiblePrevious($envVar)) {

                    throw new \EnvForm\Exceptions\BackToMenuException;
                }

                Header::render();

                throw new FormRevertedException;
            }

        });

        return $prompt->prompt();

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
