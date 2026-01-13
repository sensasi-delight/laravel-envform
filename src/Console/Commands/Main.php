<?php

declare(strict_types=1);

namespace EnvForm\Console\Commands;

use EnvForm\Services\ConfigAnalyzer;
use EnvForm\Services\EnvFileHelper;
use EnvForm\Services\EnvReader;
use EnvForm\Services\EnvWriter;
use EnvForm\Services\InteractiveWizard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
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
        ConfigAnalyzer $analyzer,
        EnvReader $envReader,
        EnvFileHelper $envFileHelper
    ): int {
        $this->displayWelcome();

        // 1. Select Target Environment File
        $this->targetEnvFile = $this->selectEnvFile($envFileHelper);

        $configPath = App::configPath();
        $this->info("🔍 Analyzing project configuration in: {$configPath}...");

        $allKeys = $analyzer->analyze($configPath);

        if ($allKeys->isEmpty()) {
            warning('⚠️  No env() calls found in config/*.php. Please check your configuration files.');

            return self::FAILURE;
        }

        note("✨ Found {$allKeys->count()} potential environment variables to configure.");

        // 2. Load Existing Env
        $envPath = App::basePath($this->targetEnvFile);
        $existingEnv = [];
        if (file_exists($envPath)) {
            note("📖 Loading existing values from [{$this->targetEnvFile}]...");
            $existingEnv = $envReader->read($envPath);
        } else {
            note("🆕 File [{$this->targetEnvFile}] does not exist. Creating a new one.");
        }

        // 3. Interactive Wizard
        $wizard = new InteractiveWizard($allKeys, $existingEnv);
        $collectedValues = $wizard->run();

        // 4. Save
        return $this->saveChanges($collectedValues, $allKeys);
    }

    private function displayWelcome(): void
    {
        info('🚀 Welcome to Laravel EnvForm!');
        note('💡 LOCAL ANALYSIS: This tool scans your config directory locally and writes directly to your .env file.');
        note('🔒 PRIVACY: No data is sent to external servers. All processing stays on your machine.');
    }

    private function selectEnvFile(EnvFileHelper $envFileHelper): string
    {
        $options = $envFileHelper->findEnvFiles(App::basePath());

        // Add option for new file
        $options['NEW'] = '➕ Create New File...';

        $choice = select(
            label: '📂 Which environment file do you want to manage?',
            options: $options,
            default: '.env'
        );

        if ($choice === 'NEW') {
            return text(
                label: '🆕 Enter the name of the new environment file:',
                default: '.env.local',
                hint: 'e.g. .env.testing, .env.staging'
            );
        }

        return (string) $choice;
    }

    /**
     * @param  array<string, mixed>  $collectedValues
     * @param  \Illuminate\Support\Collection<string, array{key: string, default: mixed, file: string, description: string, group: string}>  $allKeys
     */
    private function saveChanges(array $collectedValues, $allKeys): int
    {
        if (empty($collectedValues)) {
            warning('⚠️  No changes to save.');

            return self::SUCCESS;
        }

        $filename = text(
            label: '📄 Enter the output filename:',
            default: $this->targetEnvFile,
            hint: 'The file will be saved in the project root.'
        );

        $targetPath = App::basePath($filename);

        if (file_exists($targetPath)) {
            if (! confirm("⚠️  File [{$filename}] already exists. Do you want to overwrite it?", default: false)) {
                warning('❌ Operation cancelled.');

                return self::SUCCESS;
            }
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
