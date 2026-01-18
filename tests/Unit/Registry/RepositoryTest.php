<?php

declare(strict_types=1);

namespace Tests\Unit\Registry;

use EnvForm\Registry\Repository;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class RepositoryTest extends TestCase
{
    private string $tempBasePath;

    private string $tempConfigPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempBasePath = __DIR__.'/temp_base';
        $this->tempConfigPath = $this->tempBasePath.'/config';

        if (! File::exists($this->tempConfigPath)) {
            File::makeDirectory($this->tempConfigPath, 0755, true);
        }

        $this->app->setBasePath($this->tempBasePath);
    }

    protected function tearDown(): void
    {
        if (File::exists($this->tempBasePath)) {
            File::deleteDirectory($this->tempBasePath);
        }
        parent::tearDown();
    }

    public function test_scan_finds_env_calls_in_config_files(): void
    {
        $content = <<<'PHP'
<?php
return [
    'name' => env('APP_NAME', 'Laravel'),
    'debug' => env('APP_DEBUG', false),
    'nested' => [
        'key' => env('NESTED_KEY'),
    ]
];
PHP;
        File::put($this->tempConfigPath.'/app.php', $content);

        $repository = new Repository;
        $results = $repository->scan();

        $this->assertCount(3, $results);

        $appName = $results->firstWhere('envKey', 'APP_NAME');
        $this->assertNotNull($appName);
        $this->assertSame('app.name', $appName['configKey']);
        $this->assertSame('Laravel', $appName['defaultValue']);
        $this->assertSame('app.php', $appName['file']);

        $appDebug = $results->firstWhere('envKey', 'APP_DEBUG');
        $this->assertNotNull($appDebug);
        $this->assertSame('app.debug', $appDebug['configKey']);
        $this->assertFalse($appDebug['defaultValue']);

        $nestedKey = $results->firstWhere('envKey', 'NESTED_KEY');
        $this->assertNotNull($nestedKey);
        $this->assertSame('app.nested.key', $nestedKey['configKey']);
        $this->assertNull($nestedKey['defaultValue']);
    }

    public function test_scan_ignores_non_env_calls(): void
    {
        $content = <<<'PHP'
<?php
return [
    'path' => base_path('foo'),
    'static' => 'value',
];
PHP;
        File::put($this->tempConfigPath.'/app.php', $content);

        $repository = new Repository;
        $results = $repository->scan();

        $this->assertEmpty($results);
    }

    public function test_scan_handles_array_item_without_keys(): void
    {
        $content = <<<'PHP'
<?php
return [
    'drivers' => [
        env('DRIVER_ONE'),
        env('DRIVER_TWO'),
    ]
];
PHP;
        File::put($this->tempConfigPath.'/drivers.php', $content);

        $repository = new Repository;
        $results = $repository->scan();

        $this->assertCount(2, $results);

        $driverOne = $results->firstWhere('envKey', 'DRIVER_ONE');
        $this->assertSame('drivers.drivers.*', $driverOne['configKey']);
    }
}
