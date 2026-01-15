<?php

declare(strict_types=1);

namespace Tests\Unit;

use EnvForm\DTO\EnvKeyDefinition;
use EnvForm\Services\ConfigAnalyzer;
use EnvForm\Services\DotEnvService;
use EnvForm\Services\KeyManager;
use Laravel\Prompts\Prompt;
use Tests\TestCase;

final class KeyManagerTest extends TestCase
{
    private KeyManager $keyManager;

    private $analyzerMock;

    private $dotEnvMock;

    protected function setUp(): void
    {
        parent::setUp();

        Prompt::fake();

        $this->analyzerMock = $this->createMock(ConfigAnalyzer::class);
        $this->dotEnvMock = $this->createMock(DotEnvService::class);

        $this->keyManager = new KeyManager(
            $this->analyzerMock,
            $this->dotEnvMock
        );
    }

    public function test_it_initializes_and_manages_form_values(): void
    {
        $this->keyManager->setFormValue('APP_NAME', 'New App');
        $this->assertEquals('New App', $this->keyManager->getFormValue('APP_NAME'));

        $this->assertArrayHasKey('APP_NAME', $this->keyManager->getFormValues());
        $this->assertEquals('New App', $this->keyManager->getFormValues()['APP_NAME']);
    }

    public function test_it_analyzes_config_keys_only_once(): void
    {
        $definitions = collect([
            new EnvKeyDefinition('KEY1', 'default', 'file.php', 'desc', 'group', 'key.1', 'current', []),
        ]);

        $this->analyzerMock->expects($this->once())
            ->method('analyze')
            ->willReturn($definitions);

        $result1 = $this->keyManager->getConfigEnvKeys();
        $result2 = $this->keyManager->getConfigEnvKeys();

        $this->assertSame($definitions, $result1);
        $this->assertSame($definitions, $result2);
    }

    public function test_it_retrieves_found_config_file_names(): void
    {
        $definitions = collect([
            new EnvKeyDefinition('KEY1', null, 'app.php', 'desc1', 'app.php', 'key1', null, []),
            new EnvKeyDefinition('KEY2', null, 'database.php', 'desc2', 'database.php', 'key2', null, []),
        ]);

        $this->analyzerMock->method('analyze')->willReturn($definitions);

        $fileNames = $this->keyManager->getFoundConfigFileNames();

        $this->assertCount(2, $fileNames);
        $this->assertTrue($fileNames->contains('app.php'));
        $this->assertTrue($fileNames->contains('database.php'));
    }

    public function test_it_retrieves_definition_by_config_key(): void
    {
        $def = new EnvKeyDefinition('APP_NAME', null, 'app.php', 'desc', 'app.php', 'app.name', null, []);
        $definitions = collect([$def]);

        $this->analyzerMock->method('analyze')->willReturn($definitions);

        $found = $this->keyManager->getDefinitionByConfigKey('app.name');
        $this->assertSame($def, $found);

        $not_found = $this->keyManager->getDefinitionByConfigKey('non.existent');
        $this->assertNull($not_found);
    }

    public function test_it_gets_dot_env_values_from_service(): void
    {
        $this->dotEnvMock->method('read')->willReturn(collect(['DB_HOST' => '127.0.0.1']));

        $this->keyManager->setTargetEnvFile('non_existent.env');
        $this->assertNull($this->keyManager->getDotEnvValue('DB_HOST'));
        $this->assertEquals(0, $this->keyManager->getCountDotEnvKeyValuePairs());
    }
}
