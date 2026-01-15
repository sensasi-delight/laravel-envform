<?php

declare(strict_types=1);

namespace Tests\Unit;

use EnvForm\Services\DotEnvService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class DotEnvServiceTest extends TestCase
{
    private string $tempFile;

    private DotEnvService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempFile = __DIR__.'/test.env';
        $this->service = new DotEnvService;
    }

    protected function tearDown(): void
    {
        if (File::exists($this->tempFile)) {
            File::delete($this->tempFile);
        }
        parent::tearDown();
    }

    public function test_it_returns_empty_collection_if_file_does_not_exist(): void
    {
        $result = $this->service->read('non_existent_file.env');
        $this->assertCount(0, $result);
    }

    public function test_it_parses_env_file_correctly(): void
    {
        $content = <<<'EOT'
APP_NAME="Laravel EnvForm"
APP_ENV=local
# This is a comment
APP_DEBUG=true
EOT;
        File::put($this->tempFile, $content);

        $result = $this->service->read($this->tempFile);

        $this->assertCount(3, $result);
        $this->assertEquals('Laravel EnvForm', $result->get('APP_NAME'));
        $this->assertEquals('local', $result->get('APP_ENV'));
        $this->assertEquals('true', $result->get('APP_DEBUG'));
    }

    public function test_it_writes_env_file_correctly(): void
    {
        $values = [
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '3306',
        ];
        $metadata = [
            'DB_HOST' => 'Database',
            'DB_PORT' => 'Database',
        ];

        $this->service->write($this->tempFile, $values, $metadata);

        $this->assertFileExists($this->tempFile);
        $content = File::get($this->tempFile);

        $this->assertStringContainsString('# --- Database ---', $content);
        $this->assertStringContainsString('DB_HOST=127.0.0.1', $content);
        $this->assertStringContainsString('DB_PORT=3306', $content);
    }

    public function test_it_comments_out_unused_keys(): void
    {
        // Setup existing .env with a key that is no longer in metadata
        File::put($this->tempFile, "OLD_KEY=some_value\nKEEP_ME=true");

        $values = [
            'KEEP_ME' => 'true',
        ];
        $metadata = [
            'KEEP_ME' => 'General',
        ];

        $this->service->write($this->tempFile, $values, $metadata);

        $content = File::get($this->tempFile);

        // KEEP_ME should be active
        $this->assertStringContainsString('KEEP_ME=true', $content);
        $this->assertStringNotContainsString('# KEEP_ME=true', $content);

        // OLD_KEY should be commented out
        $this->assertStringContainsString('# OLD_KEY=some_value', $content);

        // Ensure it's not active
        $this->assertStringNotContainsString("\nOLD_KEY=some_value", "\n".$content);
    }
}
