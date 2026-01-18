<?php

declare(strict_types=1);

namespace Tests\Unit\Hint;

use EnvForm\Hint\Repository;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class RepositoryTest extends TestCase
{
    private string $tempPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempPath = __DIR__.'/temp_hints';
        File::makeDirectory($this->tempPath, 0755, true, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempPath);
        parent::tearDown();
    }

    public function test_it_returns_hint_from_file(): void
    {
        $content = <<<'PHP'
<?php
return [
    'app.name' => 'The application name',
];
PHP;
        File::put($this->tempPath.'/hints.php', $content);

        $repo = new Repository([$this->tempPath]);

        $this->assertEquals('The application name', $repo->get('app.name'));
    }

    public function test_it_returns_empty_string_if_key_not_found(): void
    {
        File::put($this->tempPath.'/hints.php', '<?php return [];');
        $repo = new Repository([$this->tempPath]);

        $this->assertEquals('', $repo->get('missing.key'));
    }

    public function test_it_handles_missing_files_gracefully(): void
    {
        $repo = new Repository([$this->tempPath.'/non_existent']);
        $this->assertEquals('', $repo->get('app.name'));
    }
}
