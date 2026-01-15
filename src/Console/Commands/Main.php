<?php

declare(strict_types=1);

namespace EnvForm\Console\Commands;

use EnvForm\Services\DotEnvService;
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
    ): int {
        clear();
        $this->displayWelcome();

        if (KeyManager::getConfigEnvKeys()->isEmpty()) {
            warning('⚠️  No env() calls found in config/*.php. Please check your configuration files.');

            return self::FAILURE;
        }

        (new InteractiveWizard)->run();

        return $this->saveChanges();
    }

    private function displayWelcome(): void
    {
        info('🚀 Welcome to Laravel EnvForm!');
        note('💡 LOCAL ANALYSIS: This tool scans your config directory locally and writes directly to your .env file.');
        note('🔒 PRIVACY: No data is sent to external servers. All processing stays on your machine.');
    }

    private function saveChanges(): int
    {
        clear();

        if (empty(KeyManager::getFormValues())) {
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

        $service = app(DotEnvService::class);
        $service->write(
            $targetPath,
            KeyManager::getFormValues(),
            KeyManager::getConfigEnvKeys()->pluck('group', 'key')->toArray()
        );
        info("✅ Successfully updated {$filename} file!");

        return self::SUCCESS;
    }
}
