<?php

declare(strict_types=1);

namespace Tests\Unit;

use EnvForm\Services\EnvReader;
use Tests\TestCase;

final class EnvReaderTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempFile = __DIR__.'/test.env';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
        parent::tearDown();
    }

    public function test_it_returns_empty_array_if_file_does_not_exist(): void
    {
        $reader = new EnvReader;
        $result = $reader->read('non_existent_file.env');

        $this->assertIsIterable($result);
        $this->assertCount(0, $result);
    }

    public function test_it_parses_env_file_correctly(): void
    {
        $content = <<<'EOT'
APP_NAME="Laravel EnvForm"
APP_ENV=local
# This is a comment
APP_DEBUG=true
DB_CONNECTION=mysql
EOT;
        file_put_contents($this->tempFile, $content);

        $reader = new EnvReader;
        $result = $reader->read($this->tempFile)->keyBy('key');

        $this->assertCount(4, $result);
        $this->assertEquals('Laravel EnvForm', $result['APP_NAME']->value);
        $this->assertEquals('local', $result['APP_ENV']->value);
        $this->assertEquals('true', $result['APP_DEBUG']->value);
        $this->assertEquals('mysql', $result['DB_CONNECTION']->value);
    }

    public function test_it_ignores_comments_and_empty_lines(): void
    {
        $content = <<<'EOT'
KEY_ONE=value1

# Comment
KEY_TWO=value2
EOT;
        file_put_contents($this->tempFile, $content);

        $reader = new EnvReader;
        $result = $reader->read($this->tempFile);

        $resultArr = $result->mapWithKeys(fn ($item) => [$item->key => $item->value])->toArray();

        $this->assertArrayHasKey('KEY_ONE', $resultArr);
        $this->assertArrayHasKey('KEY_TWO', $resultArr);

        $this->assertIsIterable($result);
        $this->assertCount(2, $result);
    }
}
