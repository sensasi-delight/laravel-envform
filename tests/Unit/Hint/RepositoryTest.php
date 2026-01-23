<?php

declare(strict_types=1);

namespace Tests\Unit\Hint;

use EnvForm\Hint\Repository;
use PHPUnit\Framework\TestCase;

final class RepositoryTest extends TestCase
{
    private string $tempPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempPath = __DIR__.'/temp_hints';
        if (! is_dir($this->tempPath)) {
            mkdir($this->tempPath, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempPath)) {
            $this->deleteDirectory($this->tempPath);
        }
        parent::tearDown();
    }

    private function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $files = array_diff(scandir($path) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $filePath = $path.'/'.$file;
            is_dir($filePath) ? $this->deleteDirectory($filePath) : unlink($filePath);
        }
        rmdir($path);
    }

    public function test_it_returns_hint_from_file(): void
    {
        $content = <<<'PHP'
<?php
return [
    'app.name' => 'The application name',
];
PHP;
        file_put_contents($this->tempPath.'/hints.php', $content);

        $repo = new Repository([$this->tempPath]);

        $this->assertEquals('The application name', $repo->get('app.name'));
    }

    public function test_it_returns_empty_string_if_key_not_found(): void
    {
        file_put_contents($this->tempPath.'/hints.php', '<?php return [];');
        $repo = new Repository([$this->tempPath]);

        $this->assertEquals('', $repo->get('missing.key'));
    }

    public function test_it_handles_missing_files_gracefully(): void
    {
        $repo = new Repository([$this->tempPath.'/non_existent']);
        $this->assertEquals('', $repo->get('app.name'));
    }
}
