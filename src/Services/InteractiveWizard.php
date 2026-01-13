<?php

declare(strict_types=1);

namespace EnvForm\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;

use function EnvForm\addLeadingWhitespace;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

final class InteractiveWizard
{
    /** @var array<string, mixed> */
    private array $collectedValues = [];

    /**
     * @param  Collection<string, array{key: string, default: mixed, file: string, description: string, group: string}>  $allKeys
     * @param  array<string, string>  $existingEnv
     */
    public function __construct(
        private readonly Collection $allKeys,
        private readonly array $existingEnv
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(): array
    {
        $groupedKeys = $this->allKeys->groupBy('group')->sortKeys();

        while (true) {
            $menuOptions = $this->buildMenuOptions($groupedKeys);

            $selectedGroup = select(
                label: '📂 Select a configuration file to configure:',
                options: $menuOptions,
                default: 'EXIT',
                scroll: \count($menuOptions)
            );

            if ($selectedGroup === 'EXIT') {
                break;
            }

            /** @var Collection<int, array{key: string, default: mixed, file: string, description: string, group: string}> $keys */
            $keys = $groupedKeys[$selectedGroup];
            $this->configureGroup((string) $selectedGroup, $keys);
        }

        return $this->collectedValues;
    }

    /**
     * @param  Collection<string, Collection<int, array{key: string, default: mixed, file: string, description: string, group: string}>>  $groupedKeys
     * @return array<string, string>
     */
    private function buildMenuOptions(Collection $groupedKeys): array
    {
        $menuOptions = [];
        foreach ($groupedKeys as $groupName => $keys) {
            $total = addLeadingWhitespace($keys->count());

            $filled = addLeadingWhitespace(
                $keys->filter(
                    function (array $item) {
                        $key = $item['key'];
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
     * @param  Collection<int, array{key: string, default: mixed, file: string, description: string, group: string}>  $keys
     */
    private function configureGroup(string $groupName, Collection $keys): void
    {
        info("🛠️  Configuring settings for: {$groupName}");

        foreach ($keys as $meta) {
            /** @var array{key: string, description: string, default: mixed} $meta */
            $this->askForValue($meta);
        }
    }

    /**
     * @param  array{key: string, description: string, default: mixed}  $meta
     */
    private function askForValue(array $meta): void
    {
        $keyName = $meta['key'];

        // Skip if special handling took care of it
        if ($this->handleSpecialKeys($keyName, $meta)) {
            return;
        }

        $currentValue = $this->collectedValues[$keyName] ?? $this->existingEnv[$keyName] ?? null;
        $defaultValue = $meta['default'];

        $label = "👉 {$keyName}";
        $hint = $meta['description'];

        if ($defaultValue !== null) {
            $displayDefault = is_bool($defaultValue) ? ($defaultValue ? 'true' : 'false') : (string) $defaultValue;
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

    /**
     * @param  array{default: mixed, description: string}  $meta
     */
    private function handleSpecialKeys(string $keyName, array $meta): bool
    {
        if ($keyName === 'APP_KEY') {
            $currentValue = $this->collectedValues[$keyName] ?? $this->existingEnv[$keyName] ?? null;
            if (confirm('🔑 Do you want to generate/regenerate APP_KEY?', default: empty($currentValue))) {
                Artisan::call('key:generate', ['--show' => true]);
                $this->collectedValues[$keyName] = trim(Artisan::output());

                return true;
            }

            return false;
        }

        $connectionConfigMap = [
            'DB_CONNECTION' => 'database.connections',
            'QUEUE_CONNECTION' => 'queue.connections',
            'BROADCAST_CONNECTION' => 'broadcasting.connections',
        ];

        if (array_key_exists($keyName, $connectionConfigMap)) {
            /** @var array<int, string> $connections */
            $connections = array_keys(Config::get($connectionConfigMap[$keyName], []));

            if (! empty($connections)) {
                $currentValue = $this->collectedValues[$keyName] ?? $this->existingEnv[$keyName] ?? null;
                $initial = $currentValue ?? $meta['default'];

                $defaultSelect = (string) $initial;
                if (! in_array($defaultSelect, $connections, true)) {
                    $defaultSelect = $connections[0];
                }

                if (! empty($defaultSelect)) {
                    $this->collectedValues[$keyName] = select(
                        label: "🔌 {$keyName}",
                        options: $connections,
                        default: $defaultSelect,
                        hint: $meta['description']
                    );

                    return true;
                }
            }
        }

        return false;
    }
}
