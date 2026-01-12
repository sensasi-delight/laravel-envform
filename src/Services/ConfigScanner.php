<?php

declare(strict_types=1);

namespace EnvForm\Services;

use Illuminate\Support\Collection;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

final class ConfigScanner
{
    private const ENV_PATTERN = "/env\(\s*['\"]([A-Z0-9_]+)['\"](?:\s*,\s*(['\"](.*?)['\"]|[^)]+))?\s*\)/";

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
            $this->extractKeysFromFile($file, $foundKeys);
        }

        return $foundKeys->sortBy('key');
    }

    /**
     * @param  Collection<string, mixed>  $foundKeys
     */
    private function extractKeysFromFile(SplFileInfo $file, Collection $foundKeys): void
    {
        $content = $file->getContents();
        $filename = $file->getFilename();

        preg_match_all(
            self::ENV_PATTERN,
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
