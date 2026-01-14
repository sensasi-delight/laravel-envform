<?php

declare(strict_types=1);

namespace EnvForm\Console\Commands;

use EnvForm\Services\DependencyResolver;
use EnvForm\Services\EnvWriter;
use EnvForm\Services\InteractiveWizard;
use EnvForm\Services\KeyManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;

use function Laravel\Prompts\clear;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

final class Main extends Command
{
    /**
     * @var string
     */
    protected $signature = 'envform';

    /**
     * @var string
     */
    protected $description = 'Interactively manage .env file based on project analysis.';

    private string $targetEnvFile = '.env';

    final public function handle(
        DependencyResolver $dependencyResolver
    ): int {
        clear();
        $this->displayWelcome();

        $allKeys = KeyManager::getConfigEnvKeys();

        if (KeyManager::getConfigEnvKeys()->isEmpty()) {
            warning('⚠️  No env() calls found in config/*.php. Please check your configuration files.');

            return self::FAILURE;
        }

        $dotEnvKeys = KeyManager::getDotEnvKeyValuePairs()
            ->mapWithKeys(fn ($item) => [$item->key => $item->value])->toArray();

        $wizard = new InteractiveWizard(
            $allKeys,
            $dotEnvKeys,
            $dependencyResolver
        );

        $collectedValues = $wizard->run();

        return $this->saveChanges(
            $collectedValues,
            $allKeys
        );
    }

    private function displayWelcome(): void
    {
        info('🚀 Welcome to Laravel EnvForm!');
        note('💡 LOCAL ANALYSIS: This tool scans your config directory locally and writes directly to your .env file.');
        note('🔒 PRIVACY: No data is sent to external servers. All processing stays on your machine.');
    }

    /**
     * @param  array<string, mixed>  $collectedValues
     * @param  \Illuminate\Support\Collection<int, \EnvForm\DTO\EnvKeyDefinition>  $allKeys
     */
    private function saveChanges(array $collectedValues, $allKeys): int
    {
        clear();

        if (empty($collectedValues)) {
            warning('⚠️  No changes to save.');

            return self::SUCCESS;
        }

        while (true) {
            clear();

            $filename = text(
                label: '📄 Enter the output filename',
                default: $this->targetEnvFile,
                hint: 'The file will be saved in the project root.'
            );

            $targetPath = App::basePath($filename);

            if (
                file_exists($targetPath) &&
                ! confirm(
                    label: "⚠️  File [{$filename}] already exists. Do you want to overwrite it?",
                    default: false
                )
            ) {
                continue;
            }

            break;
        }

        $writer = new EnvWriter($targetPath);
        $writer->update(
            $collectedValues,
            $allKeys->pluck('group', 'key')->toArray()
        );
        info("✅ Successfully updated {$filename} file!");

        return self::SUCCESS;
    }
}
