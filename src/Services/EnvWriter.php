<?php

declare(strict_types=1);

namespace EnvForm\Services;

use Illuminate\Support\Facades\File;

final class EnvWriter
{
    final public function __construct(
        protected string $path
    ) {}

    /**
     * @param  array<string, mixed>  $values
     */
    final public function update(array $values): void
    {
        $currentLines = $this->getExistingLines();

        [$newContent, $processedKeys] = $this->processExistingLines($currentLines, $values);

        $newContent = $this->appendNewKeys($newContent, $values, $processedKeys);

        File::put($this->path, implode("\n", $newContent));
    }

    /**
     * @return array<int, string>
     */
    private function getExistingLines(): array
    {
        if (! File::exists($this->path)) {
            return [];
        }
        $content = File::get($this->path);

        return explode("\n", $content);
    }

    /**
     * @param  array<int, string>  $lines
     * @param  array<string, mixed>  $values
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function processExistingLines(array $lines, array $values): array
    {
        $newContent = [];
        $processedKeys = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($this->shouldSkipLine($line)) {
                $newContent[] = $line;

                continue;
            }

            $parts = explode('=', $line, 2);
            $key = trim($parts[0]);

            if (array_key_exists($key, $values)) {
                $val = $this->formatValue($values[$key]);
                $newContent[] = "{$key}={$val}";
                $processedKeys[] = $key;
            } else {
                $newContent[] = $line;
            }
        }

        return [$newContent, $processedKeys];
    }

    private function shouldSkipLine(string $line): bool
    {
        return empty($line) || str_starts_with($line, '#');
    }

    /**
     * @param  array<int, string>  $content
     * @param  array<string, mixed>  $values
     * @param  array<int, string>  $processedKeys
     * @return array<int, string>
     */
    private function appendNewKeys(array $content, array $values, array $processedKeys): array
    {
        $addedSpacing = false;

        foreach ($values as $key => $value) {
            if (in_array($key, $processedKeys, true)) {
                continue;
            }

            if (! $addedSpacing && ! empty($content)) {
                $content[] = '';
                $addedSpacing = true;
            }

            $val = $this->formatValue($value);
            $content[] = "{$key}={$val}";
        }

        return $content;
    }

    private function formatValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_null($value)) {
            return 'null';
        }

        $stringVal = (string) $value;

        if (str_contains($stringVal, ' ') || str_contains($stringVal, '#')) {
            return '"'.$stringVal.'"';
        }

        return $stringVal;
    }
}
