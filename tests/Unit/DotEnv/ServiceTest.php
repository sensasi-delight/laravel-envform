<?php

declare(strict_types=1);

namespace Tests\Unit\DotEnv;

use EnvForm\DotEnv\Formatter;
use EnvForm\DotEnv\Repository;
use EnvForm\DotEnv\Service;
use EnvForm\FormValue\Service as FormValueService;
use EnvForm\Registry\RepositoryContract;
use EnvForm\Registry\Service as RegistryService;
use EnvForm\ShouldAsk\RepositoryContract as ShouldAskRepositoryContract;
use EnvForm\ShouldAsk\Service as ShouldAskService;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

final class ServiceTest extends TestCase
{
    private string $tempEnvFile;

    protected function setUp(): void
    {
        parent::setUp();
        // Orchestra's basePath is usually the workbench or vendor dir.
        // We will override it to current dir for simplicity of file checking.
        $this->assertNotNull($this->app);
        $this->app->setBasePath(__DIR__);
        $this->tempEnvFile = __DIR__.'/.env.test';
    }

    protected function tearDown(): void
    {
        if (File::exists($this->tempEnvFile)) {
            File::delete($this->tempEnvFile);
        }
        parent::tearDown();
    }

    public function test_save_writes_correct_content_with_dependency_logic(): void
    {
        // Setup Registry Findings
        $findings = collect([
            [
                'envKey' => 'APP_NAME',
                'configKey' => 'app.name',
                'defaultValue' => 'Laravel',
                'file' => 'app.php',
            ],
            [
                'envKey' => 'CACHE_DRIVER',
                'configKey' => 'cache.default',
                'defaultValue' => 'file',
                'file' => 'cache.php',
            ],
            [
                'envKey' => 'REDIS_HOST',
                'configKey' => 'cache.stores.redis.host',
                'defaultValue' => '127.0.0.1',
                'file' => 'cache.php',
            ],
        ]);

        /** @var RepositoryContract&MockObject $regRepo */
        $regRepo = $this->createMock(RepositoryContract::class);
        $regRepo->method('scan')->willReturn($findings);
        $regRepo->method('getDependencyMap')->willReturn([
            'cache.stores.redis.*' => 'cache.default',
        ]);
        $registry = new RegistryService($regRepo);

        // Setup Form Values
        $formValue = new FormValueService;
        $formValue->set('APP_NAME', 'Test App');
        $formValue->set('CACHE_DRIVER', 'file'); // This should make REDIS_HOST skipped
        $formValue->set('REDIS_HOST', '127.0.0.1'); // Even if set, it should be commented out if skipped

        // Setup DotEnv Components
        $repo = new Repository;
        $formatter = new Formatter;

        /** @var ShouldAskRepositoryContract&MockObject $depRepo */
        $depRepo = $this->createMock(ShouldAskRepositoryContract::class);
        $depRepo->method('getMap')->willReturn([
            'cache.stores.*' => 'cache.default',
        ]);

        $shouldAsk = new ShouldAskService($formValue, $registry, $depRepo);

        $service = new Service($formValue, $registry, $shouldAsk, $repo, $formatter);
        $service->setTargetFile('.env.test');

        $service->save();

        $this->assertFileExists($this->tempEnvFile);
        $content = File::get($this->tempEnvFile);

        // APP_NAME should be present
        $this->assertStringContainsString('APP_NAME="Test App"', $content);

        // CACHE_DRIVER should be present
        $this->assertStringContainsString('CACHE_DRIVER=file', $content);

        // REDIS_HOST should be commented out because CACHE_DRIVER=file (dependency not met)
        // Format: "# REDIS_HOST=..."
        $this->assertStringContainsString('# REDIS_HOST=127.0.0.1', $content);
    }
}
