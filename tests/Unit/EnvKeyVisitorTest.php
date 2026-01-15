<?php

declare(strict_types=1);

namespace Tests\Unit;

use EnvForm\Visitors\EnvKeyVisitor;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Tests\TestCase;

final class EnvKeyVisitorTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
    }

    public function test_it_finds_basic_env_call(): void
    {
        $code = "<?php return ['key' => env('APP_NAME')];";
        $items = $this->traverse($code);

        $this->assertCount(1, $items);
        $this->assertEquals('APP_NAME', $items[0]->key);
        $this->assertNull($items[0]->default);
    }

    public function test_it_finds_env_call_with_string_default(): void
    {
        $code = "<?php return ['key' => env('APP_NAME', 'Laravel')];";
        $items = $this->traverse($code);

        $this->assertEquals('Laravel', $items[0]->default);
    }

    public function test_it_finds_env_call_with_boolean_default(): void
    {
        $code = "<?php return [
            'a' => env('DEBUG_TRUE', true),
            'b' => env('DEBUG_FALSE', false),
        ];";
        $items = $this->traverse($code);

        $this->assertTrue($items[0]->default);
        $this->assertFalse($items[1]->default);
    }

    public function test_it_finds_env_call_with_null_default(): void
    {
        $code = "<?php return ['key' => env('APP_KEY', null)];";
        $items = $this->traverse($code);

        $this->assertNull($items[0]->default);
    }

    public function test_it_finds_env_call_with_numeric_default(): void
    {
        $code = "<?php return ['port' => env('DB_PORT', 3306)];";
        $items = $this->traverse($code);

        $this->assertSame(3306, $items[0]->default);
    }

    public function test_it_resolves_nested_config_keys(): void
    {
        $code = "<?php return [
            'connections' => [
                'mysql' => [
                    'host' => env('DB_HOST', 'localhost'),
                ]
            ]
        ];";

        // We simulate scanning 'database.php'
        $items = $this->traverse($code, 'database');

        $this->assertEquals('database.connections.mysql.host', $items[0]->configKey);
    }

    public function test_it_handles_indexed_arrays(): void
    {
        $code = "<?php return [
            'drivers' => [
                env('DRIVER_ONE'),
                env('DRIVER_TWO'),
            ]
        ];";

        $items = $this->traverse($code, 'app');

        // Logic in Visitor: $this->stack[] = '*' for indexed arrays
        $this->assertEquals('app.drivers.*', $items[0]->configKey);
        $this->assertEquals('app.drivers.*', $items[1]->configKey);
    }

    private function traverse(string $code, string $filename = 'app'): \Illuminate\Support\Collection
    {
        $ast = $this->parser->parse($code);
        $visitor = new EnvKeyVisitor($filename);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->getFoundItems();
    }
}
