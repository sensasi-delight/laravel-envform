<?php

declare(strict_types=1);

namespace EnvForm\Console\Commands;

use EnvForm\Services\EnvFile;
use EnvForm\Services\EnvironmentState;
use EnvForm\Services\Wizard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;

use function Laravel\Prompts\clear;
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

    final public function __construct(
        private readonly Wizard $wizard,
        private readonly EnvFile $envFile,
        private readonly EnvironmentState $state
    ) {
        parent::__construct();
    }

    final public function handle(): int
    {
        clear();
        $this->displayWelcome();

        $envFile = $this->selectEnvFile();
        $this->state->setTargetEnvFile($envFile);
        $this->targetEnvFile = $envFile;

        if ($this->state->all()->isEmpty()) {
            warning('⚠️  No env() calls found in config/*.php. Please check your configuration files.');

            return self::FAILURE;
        }

        $this->wizard->run();

        return $this->saveChanges();
    }

    private function selectEnvFile(): string
    {
        $options = $this->envFile->findFiles(App::basePath());

        // Add option for new file
        $options['NEW'] = '➕ Create New File...';

        $choice = select(
            label: '📂 Which environment file do you want to manage?',
            options: $options,
            default: '.env'
        );

        if ($choice === 'NEW') {
            return text(
                label: '🆕 Enter the name of the new environment file',
                default: '.env.local',
                hint: 'e.g. .env.testing, .env.staging'
            );
        }

        return (string) $choice;
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

        $finalValues = $this->state->getFinalValues();

        if (empty($finalValues)) {
            warning('⚠️  No values to save.');

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

        $metadata = $this->state->all()->pluck('group', 'key')->toArray();

        $this->envFile->write(
            $targetPath,
            $finalValues,
            $metadata
        );
        info("✅ Successfully updated {$filename} file!");

        return self::SUCCESS;
    }
}
