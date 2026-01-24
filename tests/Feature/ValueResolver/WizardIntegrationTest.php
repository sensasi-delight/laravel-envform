<?php

declare(strict_types=1);

namespace Tests\Feature\ValueResolver;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class WizardIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! File::isDirectory(config_path())) {
            File::makeDirectory(config_path(), 0755, true);
        }

        // Mock a cache config that uses DB_CACHE_LOCK_TABLE
        File::put(config_path('cache.php'), '<?php return [
            "default" => env("CACHE_STORE", "database"),
            "stores" => [
                "database" => [
                    "table" => env("DB_CACHE_TABLE", "cache"),
                    "lock_table" => env("DB_CACHE_LOCK_TABLE", "cache_lock"),
                ],
            ],
        ];');

        // Create an empty .env
        File::put(base_path('.env'), 'DB_CACHE_TABLE=my_table');
    }

    protected function tearDown(): void
    {
        File::delete(config_path('cache.php'));
        File::delete(base_path('.env'));
        parent::tearDown();
    }

    final public function test_it_suggests_implicit_lock_table_value(): void
    {
        // We expect that DB_CACHE_LOCK_TABLE will be suggested as "my_table_lock"
        // because DB_CACHE_TABLE is set to "my_table" in .env.

        /** @phpstan-ignore method.nonObject */
        $this->artisan('envform')
            ->expectsQuestion('📂 Which environment file do you want to manage?', '.env')
            // Select cache.php
            ->expectsQuestion('📂 Select a configuration file to configure', 'cache')
            // It should ask for all three variables in the group.
            ->expectsQuestion('🚀  [1/3] CACHE_STORE', 'database')
            ->expectsQuestion('⚙️  [2/3] DB_CACHE_LOCK_TABLE', 'my_table_lock')
            ->expectsQuestion('⚙️  [3/3] DB_CACHE_TABLE', 'my_table')
            ->expectsQuestion('📂 Select a configuration file to configure', 'exit')
            ->expectsQuestion('📄 Enter the output filename', '.env')
            ->expectsQuestion('⚠️ File [.env] already exists. Do you want to overwrite it?', true)
            ->assertExitCode(0);
    }
}
