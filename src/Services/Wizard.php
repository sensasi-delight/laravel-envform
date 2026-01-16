<?php

declare(strict_types=1);

namespace EnvForm\Services;

use EnvForm\Contracts\UserSessionService;
use EnvForm\DTO\EnvVar;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;

use function Laravel\Prompts\clear;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

/**
 * Terminal UI (TUI) orchestrator for the interactive configuration process.
 * Manages the prompt loop, progress display, and navigation between configuration groups.
 */
final class Wizard
{
    final public function __construct(
        private readonly RuleEngine $ruleEngine,
        private readonly EnvRegistry $registry,
        private readonly UserSessionService $session,
        private readonly EnvManager $envManager
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
        $fileNames = $this->registry->groups();

        $menuOptions = [];
        foreach ($fileNames as $fileName) {
            $envVars = $this->registry->all()->filter(fn (EnvVar $v) => $v->group === $fileName);
            $askVars = $envVars->filter(fn (EnvVar $v) => $this->ruleEngine->shouldAsk($v));

            $total = str_pad((string) $askVars->count(), 2, ' ', STR_PAD_LEFT);

            $filled = str_pad(
                (string) $askVars->filter(
                    function (EnvVar $item) {
                        $key = $item->key;
                        $val = $this->session->input($key)
                            ?? $this->envManager->getExistingValue($key);

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

        $vars = $this->registry->all()
            ->filter(fn (EnvVar $v) => $v->group === $groupName)
            ->filter(fn (EnvVar $v) => $this->ruleEngine->shouldAsk($v));

        foreach ($vars->filter(fn ($v) => $v->isTrigger) as $envVar) {
            $this->askForValue($envVar);
        }

        foreach ($vars->filter(fn ($v) => ! $v->isTrigger) as $envVar) {
            $this->askForValue($envVar);
        }
    }

    private function askForValue(EnvVar $envVar): void
    {
        if (! $this->ruleEngine->shouldAsk($envVar)) {
            return;
        }

        if (
            $this->handleAppKey($envVar->key) ||
            $this->handleStrictKeys($envVar)
        ) {
            return;
        }

        $currentValue = $this->session->input($envVar->key)
            ?? Config::get($envVar->configKey)
            ?? $this->envManager->getExistingValue($envVar->key);

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

            $this->session->set(
                $envVar->key,
                confirm(
                    label: $label,
                    default: (bool) $boolInitial,
                    hint: $hint
                )
            );

            return;
        }

        $this->session->set(
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

        $currentValue = $this->session->input($keyName)
            ?? $this->envManager->getExistingValue($keyName);

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

        $this->session->set(
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

        $this->session->set(
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

        $defaultValue = $this->session->input($envVar->key)
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
        $summary = $this->envManager->getSummary();

        table(
            ['Summary', ''],
            [
                ['ENV vars need configuration', (string) $summary['pending']],
                ['Existing vars in file', (string) $summary['existing']],
            ]
        );
    }
}
