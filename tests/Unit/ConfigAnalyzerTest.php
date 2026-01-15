<?php

declare(strict_types=1);

namespace Tests\Unit;

use EnvForm\DTO\EnvKeyDefinition;
use EnvForm\Services\ConfigAnalyzer;
use EnvForm\Services\ConfigParser;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Laravel\Prompts\Prompt;
use Tests\TestCase;

final class ConfigAnalyzerTest extends TestCase
{
    private ConfigAnalyzer $analyzer;

    private $parserMock;

    private string $tempConfigPath;

    protected function setUp(): void
    {
        parent::setUp();

        Prompt::fake();

        $this->tempConfigPath = __DIR__.'/temp_config';
        if (! is_dir($this->tempConfigPath)) {
            mkdir($this->tempConfigPath);
        }

        $this->parserMock = $this->createMock(ConfigParser::class);
        $this->analyzer = new ConfigAnalyzer($this->parserMock);

        // Mock App::configPath()
        App::shouldReceive('configPath')->andReturn($this->tempConfigPath);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempConfigPath)) {
            File::deleteDirectory($this->tempConfigPath);
        }
        parent::tearDown();
    }

    public function test_it_extracts_env_keys_from_config_files(): void
    {
        $content = "<?php return ['name' => env('APP_NAME', 'Laravel')];";
        File::put($this->tempConfigPath.'/app.php', $content);

        $this->parserMock->method('parse')->willReturn(collect([
            new EnvKeyDefinition('APP_NAME', 'Laravel', 'app.php', '', 'app', 'app.name', null, []),
        ]));

        $result = $this->analyzer->analyze();

        $this->assertCount(1, $result);
        $this->assertEquals('APP_NAME', $result->first()->key);
        $this->assertEquals('Laravel', $result->first()->default);
    }

    public function test_it_guesses_descriptions_correctly(): void
    {
        // Internal guessDescription is private, so we test it via analyze()
        $content = "<?php return ['host' => env('DB_HOST')];";
        File::put($this->tempConfigPath.'/db.php', $content);

        // We must provide an AST result for DB_HOST, otherwise it will be filtered out as "commented out"
        $this->parserMock->method('parse')->willReturn(collect([
            new EnvKeyDefinition('DB_HOST', null, 'db.php', '', 'db', 'db.host', null, []),
        ]));

        $result = $this->analyzer->analyze();

        $this->assertCount(1, $result);
        $this->assertStringContainsString('Host address', $result->first()->description);
    }

    public function test_it_parses_default_values_correctly(): void
    {
        $content = "<?php return [
                'key1' => env('KEY_NULL', null),
                'key2' => env('KEY_TRUE', true),
                'key3' => env('KEY_FALSE', false),
                'key4' => env('KEY_STRING', 'hello'),
            ];";
        File::put($this->tempConfigPath.'/test.php', $content);

        // Mock AST finding all these keys
        $this->parserMock->method('parse')->willReturn(collect([
            new EnvKeyDefinition('KEY_NULL', null, 'test.php', '', 'test', 'key1', null, []),
            new EnvKeyDefinition('KEY_TRUE', true, 'test.php', '', 'test', 'key2', null, []),
            new EnvKeyDefinition('KEY_FALSE', false, 'test.php', '', 'test', 'key3', null, []),
            new EnvKeyDefinition('KEY_STRING', 'hello', 'test.php', '', 'test', 'key4', null, []),
        ]));

        $result = $this->analyzer->analyze();

        $this->assertCount(4, $result);

        $this->assertNull($result->firstWhere('key', 'KEY_NULL')->default);
        $this->assertTrue($result->firstWhere('key', 'KEY_TRUE')->default);
        $this->assertFalse($result->firstWhere('key', 'KEY_FALSE')->default);
        $this->assertEquals('hello', $result->firstWhere('key', 'KEY_STRING')->default);
    }

    public function test_it_ignores_keys_found_by_regex_but_missing_in_ast(): void
    {
        // Scenario: 'APP_DEBUG' is commented out in the file.
        // Regex finds it. AST does NOT find it.
        // Expected: Analyzer should discard it.

        $content = <<<'PHP'
    <?php
    return [
        // 'debug' => env('APP_DEBUG', false),
        'name' => env('APP_NAME', 'Laravel'),
    ];
    PHP;
        File::put($this->tempConfigPath.'/commented.php', $content);

        // AST Mock returns only APP_NAME
        $this->parserMock->method('parse')->willReturn(collect([
            new EnvKeyDefinition('APP_NAME', 'Laravel', 'commented.php', '', 'commented', 'name', null, []),
        ]));

        $result = $this->analyzer->analyze();

        // Should only have 1 (APP_NAME), APP_DEBUG should be gone.
        $this->assertCount(1, $result);
        $this->assertEquals('APP_NAME', $result->first()->key);
    }
}
