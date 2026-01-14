<?php

declare(strict_types=1);

namespace Tests\Unit;

use EnvForm\Services\ConfigParser;
use Tests\TestCase;

final class ConfigParserTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = __DIR__.'/config_temp';
        if (! is_dir($this->tempDir)) {
            mkdir($this->tempDir);
        }
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob("$this->tempDir/*.*"));
        rmdir($this->tempDir);
        parent::tearDown();
    }

    public function test_it_parses_simple_config_file(): void
    {
        $content = <<<'EOT'
<?php
return [
    'name' => env('APP_NAME', 'Laravel'),
    'debug' => env('APP_DEBUG', false),
];
EOT;
        file_put_contents($this->tempDir.'/app.php', $content);

        $parser = new ConfigParser;
        $result = $parser->parse($this->tempDir);

        $this->assertCount(2, $result);

        $name = $result->firstWhere('key', 'APP_NAME');
        $this->assertEquals('app.name', $name->configKey);

        $debug = $result->firstWhere('key', 'APP_DEBUG');
        $this->assertEquals('app.debug', $debug->configKey);
    }

    public function test_it_parses_nested_config_arrays(): void
    {
        $content = <<<'EOT'
<?php
return [
    'default' => env('CACHE_STORE', 'database'),
    'stores' => [
        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
        ],
    ],
];
EOT;
        file_put_contents($this->tempDir.'/cache.php', $content);

        $parser = new ConfigParser;
        $result = $parser->parse($this->tempDir);

        $store = $result->firstWhere('key', 'CACHE_STORE');
        $this->assertEquals('cache.default', $store->configKey);

        $redis = $result->firstWhere('key', 'REDIS_CACHE_CONNECTION');
        $this->assertEquals('cache.stores.redis.connection', $redis->configKey);
    }
}
