<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class EnvFormCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create a dummy config file for analysis
        if (! File::isDirectory(config_path())) {
            File::makeDirectory(config_path(), 0755, true);
        }

        File::put(config_path('test.php'), '<?php return ["key" => env("TEST_KEY", "default")];');
    }

    protected function tearDown(): void
    {
        File::delete(config_path('test.php'));
        parent::tearDown();
    }

    public function test_it_can_run_dry_run(): void
    {
        /** @phpstan-ignore method.nonObject */
        $this->artisan('envform', ['--dry-run' => true])
            ->expectsQuestion('📂 Which .env file do you want to manage?', '.env')
            ->expectsQuestion('📂 Select a configuration file to configure', 'exit')
            ->assertExitCode(0);
    }
}
