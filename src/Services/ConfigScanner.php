<?php

declare(strict_types=1);

namespace EnvForm\Services;

use Illuminate\Support\Collection;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

final class ConfigScanner
{
    /**
     * Scan config directory for env() calls.
     *
     * @return Collection<string, array{
     *  key: string,
     *  default: mixed,
     *  file: string,
     *  description: string,
     *  group: string
     * }>
     */
    final public function scan(string $configPath): Collection
    {
        $files = Finder::create()
            ->files()
            ->in($configPath)
            ->name('*.php');

        $foundKeys = collect();

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            $content = $file->getContents();
            $filename = $file->getFilename();

            preg_match_all(
                "/env\(\s*['\"]([A-Z0-9_]+)['\"](?:\s*,\s*(['\"](.*?)['\"]|[^)]+))?\s*\)/",
                $content,
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $match) {
                /** @var non-empty-string $key */
                $key = $match[1];

                /** @var non-empty-string|null $defaultRaw */
                $defaultRaw = $match[2] ?? null;

                $default = $this->parseDefaultValue($defaultRaw);

                if (! $foundKeys->has($key)) {
                    $foundKeys->put($key, [
                        'key' => $key,
                        'default' => $default,
                        'file' => $filename,
                        'description' => $this->guessDescription($key),
                        'group' => $filename,
                    ]);
                }
            }
        }

        return $foundKeys->sortBy('key');
    }

    private function parseDefaultValue(?string $raw): mixed
    {
        if ($raw === null) {
            return null;
        }

        $default = trim($raw, "'\" ");

        return match (strtoupper($default)) {
            'NULL' => null,
            'TRUE' => true,
            'FALSE' => false,
            default => $default,
        };
    }

    private function guessDescription(string $key): string
    {
        return match (true) {
            str_contains($key, '_HOST') => 'Host address / IP',
            str_contains($key, '_PORT') => 'Port number',
            str_contains($key, '_DATABASE') => 'Database name',
            str_contains($key, '_USERNAME') => 'Username / Access Key ID',
            str_contains($key, '_PASSWORD') => 'Password / Secret Key',
            str_contains($key, '_URL') => 'Service URL',
            default => 'Configuration value for '.$key,
        };
    }
}
