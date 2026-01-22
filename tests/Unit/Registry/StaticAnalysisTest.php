<?php

declare(strict_types=1);

namespace Tests\Unit\Registry;

use EnvForm\Registry\Repository;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class StaticAnalysisTest extends TestCase
{
    private Repository $repository;

    private string $configPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new Repository;
        $this->configPath = base_path('config_test');

        if (! File::isDirectory($this->configPath)) {
            File::makeDirectory($this->configPath, 0755, true);
        }

        App::shouldReceive('configPath')
            ->andReturnUsing(fn ($path = '') => $path ? $this->configPath.DIRECTORY_SEPARATOR.$path : $this->configPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->configPath);
        parent::tearDown();
    }

    public function test_it_resolves_literal_values(): void
    {
        File::put($this->configPath.'/app.php', '<?php return ["name" => "Laravel", "debug" => true, "port" => 8080];');

        $this->assertEquals('Laravel', $this->repository->getStaticValue('app', 'name'));
        $this->assertEquals(true, $this->repository->getStaticValue('app', 'debug'));
        $this->assertEquals(8080, $this->repository->getStaticValue('app', 'port'));
    }

    public function test_it_resolves_nested_values(): void
    {
        File::put($this->configPath.'/database.php', '<?php return ["connections" => ["mysql" => ["driver" => "mysql"]]];');

        $this->assertEquals('mysql', $this->repository->getStaticValue('database', 'connections.mysql.driver'));
    }

    public function test_it_resolves_default_from_env_call(): void
    {
        File::put($this->configPath.'/database.php', '<?php return ["default" => env("DB_CONNECTION", "sqlite")];');

        // CURRENT FAILURE: Returns null because it doesn't parse FuncCall
        $this->assertEquals('sqlite', $this->repository->getStaticValue('database', 'default'));
    }

    public function test_it_resolves_class_constants(): void
    {
        File::put($this->configPath.'/app.php', '<?php return ["model" => \App\Models\User::class];');

        $this->assertEquals('App\Models\User', $this->repository->getStaticValue('app', 'model'));
    }

    public function test_it_returns_null_for_missing_keys(): void
    {
        File::put($this->configPath.'/app.php', '<?php return ["name" => "Laravel"];');

        $this->assertNull($this->repository->getStaticValue('app', 'invalid'));
        $this->assertNull($this->repository->getStaticValue('invalid_file', 'name'));
    }

    public function test_it_gets_static_keys_from_array(): void
    {
        File::put($this->configPath.'/cache.php', '<?php return ["stores" => ["redis" => [], "file" => []]];');

        $keys = $this->repository->getStaticKeys('cache', 'stores');
        $this->assertEquals(['redis', 'file'], $keys);
    }
}
