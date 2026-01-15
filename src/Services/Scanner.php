<?php

declare(strict_types=1);

namespace EnvForm\Services;

use EnvForm\DTO\EnvVar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Static analysis engine for Laravel configuration files.
 * Uses a hybrid approach (Regex + AST) to discover env() calls and map them to config paths.
 */
class Scanner
{
    private const ENV_PATTERN = "/env\(\s*['\"]([A-Z0-9_]+)['\"](?:\s*,\s*(['\"](.*?)['\"]|[^)]+))?\s*\)/";

    public function __construct() {}

    /**
     * Scan config directory for env() calls.
     *
     * @return Collection<int, EnvVar>
     */
    public function scan(): Collection
    {
        $configPath = App::configPath();

        info("🔍 Analyzing project configuration in: {$configPath}...");

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
        $astRaw = $this->parseConfigDirectory($configPath);

        $astMap = $astRaw->mapToGroups(
            fn (EnvVar $item) => [
                $item->key => $item->configKey,
            ]
        );

        // 3. Merge
        return $foundKeys
            ->filter(fn (array $item) => $astMap->has($item['key']))
            ->map(
                function (array $item) use ($astMap) {
                    $paths = $astMap->get($item['key']);

                    $configKeys = $paths ? $paths->all() : [];
                    $configKey = $paths ? $paths->first() : '';

                    // Calculate isTrigger
                    $isTrigger = false;
                    foreach ($configKeys as $ck) {
                        if (array_key_exists($ck, RuleEngine::RULES)) {
                            $isTrigger = true;
                            break;
                        }
                    }

                    // Calculate dependencies
                    $dependencies = [];
                    foreach (RuleEngine::RULES as $triggerKey => $conditions) {
                        foreach ($conditions as $triggerValue => $patterns) {
                            foreach ($configKeys as $ck) {
                                foreach ($patterns as $pattern) {
                                    if (fnmatch($pattern, $ck)) {
                                        $dependencies[$triggerKey][$triggerValue] = $patterns;
                                        // Once we find a match for this trigger+value, no need to check other patterns for this value
                                        break;
                                    }
                                }
                            }
                        }
                    }

                    return new EnvVar(
                        key: $item['key'],
                        default: $item['default'],
                        file: $item['file'],
                        description: $item['description'],
                        group: $item['group'],
                        configKeys: $configKeys,
                        configKey: (string) $configKey,
                        currentValue: $configKey ? Config::get($configKey) : null,
                        isTrigger: $isTrigger,
                        dependencies: $dependencies,
                    );
                }
            )->sortBy('key');
    }

    /**
     * @return Collection<int, EnvVar>
     */
    private function parseConfigDirectory(string $configPath): Collection
    {
        $files = Finder::create()
            ->files()
            ->in($configPath)
            ->name('*.php');

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        /** @var Collection<int, EnvVar> $foundItems */
        $foundItems = new Collection;

        foreach ($files as $file) {
            $fileItems = $this->parseFile($file, $parser);
            $foundItems = $foundItems->merge($fileItems);
        }

        return $foundItems;
    }

    /**
     * @param  \PhpParser\Parser  $parser
     * @return Collection<int, EnvVar>
     */
    private function parseFile(SplFileInfo $file, $parser): Collection
    {
        try {
            $stmts = $parser->parse($file->getContents());
            if ($stmts === null) {
                return new Collection;
            }
        } catch (\Throwable $e) {
            return new Collection;
        }

        $traverser = new NodeTraverser;
        $visitor = new EnvKeyVisitor($file->getFilenameWithoutExtension());

        $traverser->addVisitor($visitor);
        $traverser->traverse($stmts);

        return $visitor->getFoundItems();
    }

    /**
     * @param  Collection<string, mixed>  $foundKeys
     */
    private function extractKeysFromFile(
        SplFileInfo $file,
        Collection $foundKeys
    ): void {
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
