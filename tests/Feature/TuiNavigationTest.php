<?php

declare(strict_types=1);

namespace Tests\Feature;

use EnvForm\DotEnv;
use EnvForm\FormValue;
use EnvForm\Hint;
use EnvForm\KeyGenerator;
use EnvForm\OptionResolver;
use EnvForm\Registry;
use EnvForm\ShouldAsk;
use EnvForm\Wizard\Service as WizardService;
use Laravel\Prompts\ConfirmPrompt;
use Laravel\Prompts\Exceptions\FormRevertedException;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\SelectPrompt;
use Laravel\Prompts\TextPrompt;
use Tests\TestCase;

class TuiNavigationTest extends TestCase
{
    private WizardService $wizard;

    private FormValue\Service $formValue;

    /** @var array<int, string|bool|null> */
    private array $answerQueue = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->formValue = new FormValue\Service;
        $registryRepo = new Registry\Repository;
        $fixturePath = (string) realpath(__DIR__.'/../Fixture');

        if ($this->app !== null) {
            $this->app->setBasePath($fixturePath);
        }

        $registry = new Registry\Service($registryRepo);
        $hintPath = (string) realpath(__DIR__.'/../../resources');
        $hint = new Hint\Service(new Hint\Repository([$hintPath]));

        $dotEnvRepository = new DotEnv\Repository;
        $dotEnvFormatter = new DotEnv\Formatter;

        $serviceDetectionRepo = new \EnvForm\ServiceDetection\Repository;

        // Dummy ValueResolver for circular dependency during setup
        $valueResolver = $this->createMock(\EnvForm\ValueResolver\ValueResolverInterface::class);

        $serviceDetection = new \EnvForm\ServiceDetection\Service($serviceDetectionRepo, $valueResolver);

        $shouldAsk = new ShouldAsk\Service($this->formValue, $registry, new ShouldAsk\Repository, $serviceDetection);

        $dotEnv = new EnvFormDotEnvServiceWrapper(
            $this->formValue,
            $registry,
            $dotEnvRepository,
            $dotEnvFormatter
        );

        $keyGen = $this->createStub(KeyGenerator\Service::class);
        $keyGen->method('generate')->willReturn('base64:new-key-mocked');

        $optionResolver = new OptionResolver\Service($registry);

        // Real ValueResolver
        $valueResolver = new \EnvForm\ValueResolver\Service(
            $dotEnv,
            $this->formValue,
            $registry,
            new \EnvForm\ValueResolver\Repository([$hintPath]) // resources directory
        );

        // Re-inject real ValueResolver into ServiceDetection if needed, but it's already set in the constructor.
        // Since it's a mock above, I'll use reflection to set it or just instantiate it correctly now.
        $serviceDetection = new \EnvForm\ServiceDetection\Service($serviceDetectionRepo, $valueResolver);

        // Re-instantiate shouldAsk with real serviceDetection
        $shouldAsk = new ShouldAsk\Service($this->formValue, $registry, new ShouldAsk\Repository, $serviceDetection);

        // Re-instantiate dotEnv with real shouldAsk
        $dotEnv = new EnvFormDotEnvServiceWrapper(
            $this->formValue,
            $registry,
            $dotEnvRepository,
            $dotEnvFormatter
        );

        $this->wizard = new WizardService(
            $dotEnv,
            $this->formValue,
            $hint,
            $registry,
            $shouldAsk,
            $keyGen,
            $optionResolver,
            $valueResolver
        );

        Prompt::fallbackWhen(true);

        $this->setupFallbacks();
    }

    private function setupFallbacks(): void
    {
        $callback = function ($prompt) {
            if (empty($this->answerQueue)) {
                return $prompt->default;
            }

            $answer = array_shift($this->answerQueue);

            if ($answer === Key::CTRL_C) {
                throw new FormRevertedException;
            }

            return $answer ?? $prompt->default;
        };

        TextPrompt::fallbackUsing($callback);
        ConfirmPrompt::fallbackUsing($callback);
        SelectPrompt::fallbackUsing($callback);
    }

    public function test_it_can_navigate_forward_linearly(): void
    {
        $this->answerQueue = [
            'database', // Select group
            'sqlite',   // DB_CONNECTION
            'database.sqlite', // DB_DATABASE
            'true',     // DB_FOREIGN_KEYS
            'exit',     // Back to Menu
            'exit',     // Save & Exit
        ];

        $this->wizard->run();

        $this->assertEquals('sqlite', $this->formValue->get('DB_CONNECTION'));
        $this->assertEquals('database.sqlite', $this->formValue->get('DB_DATABASE'));
    }

    public function test_it_re_evaluates_dependencies_dynamically(): void
    {
        $this->answerQueue = [
            'database',      // Select group
            'sqlite',        // DB_CONNECTION (Trigger)
            'test.sqlite',   // DB_DATABASE
            Key::CTRL_C,     // At DB_FOREIGN_KEYS, press Esc
            Key::CTRL_C,     // At DB_DATABASE, press Esc
            'mysql',         // Change DB_CONNECTION to mysql (Trigger)
            'utf8mb4',       // DB_CHARSET
            'utf8mb4_unicode_ci', // DB_COLLATION
            'database_name', // DB_DATABASE
            true,            // DB_FOREIGN_KEYS
            '127.0.0.1',     // DB_HOST
            '',              // DB_PASSWORD
            '3306',          // DB_PORT
            '',              // DB_SOCKET
            '',              // DB_URL
            'root',          // DB_USERNAME
            'exit',          // Back to Menu
            'exit',          // Save & Exit
        ];

        $this->wizard->run();

        $this->assertEquals('mysql', $this->formValue->get('DB_CONNECTION'));
        $this->assertEquals('127.0.0.1', $this->formValue->get('DB_HOST'));
    }

    public function test_it_supports_app_key_generation(): void
    {
        $this->answerQueue = [
            'app', // Main Menu
            'Laravel',
            'local',
            true,
            'http://localhost:8000',
            'en',
            'en',
            'en_US',
            true, // APP_KEY Yes
            '',
            'file',
            'database',
            'exit',
            'exit',
        ];

        $this->wizard->run();

        $this->assertEquals('base64:new-key-mocked', $this->formValue->get('APP_KEY'));
    }
}

/**
 * A wrapper to avoid actually writing files during tests.
 */
class EnvFormDotEnvServiceWrapper extends \EnvForm\DotEnv\Service
{
    public function save(\EnvForm\ShouldAsk\Service $shouldAsk): void
    {
        // Do nothing
    }
}
