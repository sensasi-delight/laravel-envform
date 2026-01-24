<?php

declare(strict_types=1);

namespace EnvForm\Console\Commands;

use EnvForm\Console\Components\Header;
use EnvForm\DotEnv;
use EnvForm\Registry;
use EnvForm\Wizard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

/**
 * The main Artisan command entry point for 'envform'.
 * Orchestrates the overall flow: analysis, interactive setup, and file persistence.
 */
final class EnvForm extends Command
{
    /**
     * @var string
     */
    protected $signature = 'envform {--dry-run}';

    /**
     * @var string
     */
    protected $description = 'Interactively manage .env file based on project analysis.';

    final public function __construct(
        private readonly DotEnv\Service $dotEnv,
        private readonly Registry\Service $registry,
        private readonly \EnvForm\ShouldAsk\Service $shouldAsk,
        private readonly Wizard\Service $wizard,
    ) {
        parent::__construct();
    }

    final public function handle(): int
    {
        Header::render(
            '💡 LOCAL ANALYSIS: This tool scans your config directory locally and writes directly to your .env file.'.PHP_EOL.
            '🔒 PRIVACY: No data is sent to external servers. All processing stays on your machine.'
        );

        if ($this->option('dry-run')) {
            note('🧪 DRY RUN MODE: No changes will be written to disk.');
        }

        $envFile = $this->selectEnvFile();
        $this->dotEnv->setTargetFile($envFile);

        if ($this->registry->all()->isEmpty()) {
            warning('⚠️ No env() calls found in config/*.php. Please check your configuration files.');

            return self::FAILURE;
        }

        $this->wizard->run();

        return $this->saveChanges();
    }

    private function selectEnvFile(): string
    {
        $options = $this->dotEnv->getEnvFileOptions();

        $options['new'] = '➕ Create New File...';

        $choice = select(
            label: '📂 Which environment file do you want to manage?',
            options: $options,
            default: '.env'
        );

        if ($choice === 'new') {
            return text(
                label: '🆕 Enter the name of the new environment file',
                default: '.env.local',
                hint: 'e.g. .env.testing, .env.staging'
            );
        }

        return (string) $choice;
    }

    private function saveChanges(): int
    {
        Header::render();

        if ($this->registry->all()->isEmpty()) {
            warning('⚠️ No values to save.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            info('✅ Dry run complete. No changes were written.');

            return self::SUCCESS;
        }

        while (true) {
            Header::render();

            $filename = text(
                label: '📄 Enter the output filename',
                default: $this->dotEnv->getTargetFile(),
                hint: 'The file will be saved in the project root.'
            );

            $this->dotEnv->setTargetFile($filename);
            $targetPath = App::basePath($filename);

            if (
                file_exists($targetPath) &&
                ! confirm(
                    label: "⚠️ File [{$filename}] already exists. Do you want to overwrite it?",
                    default: false
                )
            ) {
                continue;
            }

            break;
        }

        $this->dotEnv->save($this->shouldAsk);
        info("✅ Successfully updated {$filename} file!");

        return self::SUCCESS;
    }
}
