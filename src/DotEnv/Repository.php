<?php

declare(strict_types=1);

namespace EnvForm\DotEnv;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

readonly class Repository
{
    final public function findDotEnvFiles(string $basePath): Finder
    {
        return Finder::create()
            ->files()
            ->in($basePath)
            ->name('.env*')
            ->depth(0)
            ->ignoreDotFiles(false);
    }

    /**
     * @return Collection<string, string>
     */
    final public function read(string $path): Collection
    {
        if (! File::exists($path)) {
            return collect();
        }

        $content = File::get($path);

        return $this->parseEnvFileContent($content);
    }

    /**
     * @return Collection<string, string>
     */
    private function parseEnvFileContent(string $content): Collection
    {
        $lines = explode("\n", $content);
        $values = collect();

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $values->put(trim($key), trim($value, "' "));
            }
        }

        return $values;
    }

    final public function write(string $path, string $content): void
    {
        File::put($path, $content);
    }
}
