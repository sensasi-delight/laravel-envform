<?php

declare(strict_types=1);

namespace EnvForm\Services;

use EnvForm\DTO\EnvKeyDefinition;
use Illuminate\Support\Collection;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

final class ConfigAnalyzer
{
    private const ENV_PATTERN = "/env\(\s*['\"]([A-Z0-9_]+)['\"](?:\s*,\s*(['\"](.*?)['\"]|[^)]+))?\s*\)/";

    public function __construct(
        private readonly ConfigParser $parser
    ) {}

    /**
     * Analyze config directory for env() calls.
     *
     * @return Collection<int, EnvKeyDefinition>
     */
    final public function analyze(string $configPath): Collection
    {
        // 1. Regex Analysis (Metadata)
        $files = Finder::create()
            ->files()
            ->in($configPath)
            ->name('*.php');

        $foundKeys = collect();

        foreach ($files as $file) {
            $this->extractKeysFromFile($file, $foundKeys);
        }

        // 2. AST Analysis (Structure)
        $astRaw = $this->parser->parse($configPath);

        $astMap = $astRaw->mapToGroups(
            fn (EnvKeyDefinition $item) => [
                $item->key => $item->configPath,
            ]
        );

        // 3. Merge
        return $foundKeys->map(
            function (array $item) use ($astMap) {
                $paths = $astMap->get($item['key']);

                $configPaths = $paths ? $paths->all() : [];
                $configPath = $paths ? $paths->first() : '';

                return new EnvKeyDefinition(
                    key: $item['key'],
                    default: $item['default'],
                    file: $item['file'],
                    description: $item['description'],
                    group: $item['group'],
                    configPath: (string) $configPath,
                    configPaths: $configPaths,
                );
            }
        )->sortBy('key');
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
                    'config_path' => '', // Initialize with empty
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
            str_contains($key, '_HOST') => '🏠 Host address / IP',
            str_contains($key, '_PORT') => '🚪 Port number',
            str_contains($key, '_DATABASE') => '🗄️ Database name',
            str_contains($key, '_USERNAME') => '👤 Username / Access Key ID',
            str_contains($key, '_PASSWORD') => '🔒 Password / Secret Key',
            str_contains($key, '_URL') => '🔗 Service URL',
            default => '⚙️ Configuration value for '.$key,
        };
    }
}
